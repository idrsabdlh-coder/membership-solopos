<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\OrderService;
use App\Services\PaymentStatusProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function pay(
        Request $request,
        string $order,
        OrderService $orders,
        PaymentGatewayInterface $gateway
    ): JsonResponse {
        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(['bank_transfer', 'qris', 'gopay', 'shopeepay'])],
        ]);

        $orders->expirePendingOrders((int) $order);

        $payment = DB::transaction(function () use ($request, $order, $validated): Payment {
            $orderModel = Order::query()
                ->where('user_id', $request->user()->getKey())
                ->whereKey($order)
                ->lockForUpdate()
                ->firstOrFail();

            if ($orderModel->status !== OrderStatus::Pending || $orderModel->expires_at->isPast()) {
                abort(409, 'Pesanan ini sudah tidak dapat dibayar.');
            }

            if ($orderModel->total < 1) {
                abort(422, 'Total pesanan harus lebih dari nol untuk pembayaran gateway.');
            }

            $latest = Payment::query()->where('order_id', $orderModel->id)->latest('id')->first();
            if ($latest && $latest->status === PaymentStatus::Pending) {
                if ($latest->method !== $validated['method']) {
                    abort(409, 'Pembayaran sudah dibuat dengan metode lain.');
                }

                if ($latest->redirect_url && $latest->snap_token) {
                    return $latest;
                }

                abort(409, 'Transaksi pembayaran sedang disiapkan. Silakan cek kembali sesaat lagi.');
            }

            $attempt = Payment::query()->where('order_id', $orderModel->id)->count() + 1;
            if ($attempt > 3) {
                abort(409, 'Batas percobaan pembayaran untuk pesanan ini telah tercapai.');
            }

            $merchantOrderId = Str::limit($orderModel->order_number, 44, '').'-P'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);

            return Payment::query()->create([
                'order_id' => $orderModel->id,
                'gateway' => 'midtrans',
                'merchant_order_id' => $merchantOrderId,
                'method' => $validated['method'],
                'amount' => $orderModel->total,
                'status' => PaymentStatus::Pending,
            ]);
        }, attempts: 3);

        if ($payment->snap_token && $payment->redirect_url) {
            return response()->json(['data' => PaymentResource::make($payment)]);
        }

        try {
            $orderModel = $payment->order()->with(['plan', 'user'])->firstOrFail();
            $transaction = $gateway->createTransaction($orderModel, $payment);
        } catch (RuntimeException $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);
            Log::warning('Midtrans transaction creation failed.', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Transaksi pembayaran belum dapat dibuat. Silakan coba lagi.',
            ], 502);
        }

        $payment->update([
            'snap_token' => $transaction['snap_token'],
            'redirect_url' => $transaction['redirect_url'],
        ]);

        return response()->json(['data' => PaymentResource::make($payment->fresh())], 201);
    }

    public function status(
        Request $request,
        string $order,
        PaymentGatewayInterface $gateway,
        PaymentStatusProcessor $processor
    ): JsonResponse {
        $orderModel = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($order)
            ->with(['payments' => fn ($query) => $query->latest('id')])
            ->firstOrFail();
        $payment = $orderModel->payments->first();

        if (! $payment) {
            abort(404, 'Pesanan belum memiliki transaksi pembayaran.');
        }

        if ($payment->status === PaymentStatus::Pending
            && $orderModel->status === OrderStatus::Pending
            && $payment->merchant_order_id) {
            try {
                $providerStatus = $gateway->fetchStatus($payment->merchant_order_id);
                $normalized = $gateway->normalizeNotification($providerStatus);

                if (($normalized['merchant_order_id'] ?? null) !== $payment->merchant_order_id) {
                    throw new RuntimeException('Midtrans status response does not match payment.');
                }

                $processor->processStatusResponse($providerStatus);
            } catch (RuntimeException $exception) {
                Log::warning('Midtrans status reconciliation failed.', [
                    'payment_id' => $payment->id,
                    'exception' => $exception::class,
                ]);

                return response()->json([
                    'message' => 'Status pembayaran belum dapat diperbarui. Silakan coba lagi.',
                ], 502);
            }
        }

        return response()->json([
            'data' => [
                'order_status' => $orderModel->fresh()->status->value,
                'payment' => PaymentResource::make($payment->fresh()),
            ],
        ]);
    }

    public function webhook(Request $request, PaymentGatewayInterface $gateway): JsonResponse
    {
        $payload = $request->json()->all();

        if (! $gateway->verifyNotification($payload)) {
            return response()->json(['message' => 'Signature not valid.'], 403);
        }

        $normalized = $gateway->normalizeNotification($payload);
        if (! is_string($normalized['merchant_order_id'] ?? null)
            || ! is_int($normalized['amount'] ?? null)
            || ! is_string($payload['transaction_id'] ?? null)) {
            return response()->json(['message' => 'Notification payload is invalid.'], 422);
        }

        $eventKey = hash('sha256', implode('|', [
            $normalized['merchant_order_id'],
            $payload['transaction_id'],
            $payload['status_code'] ?? '',
            $payload['gross_amount'] ?? '',
            $payload['transaction_status'] ?? '',
            $payload['fraud_status'] ?? '',
            $payload['settlement_time'] ?? '',
        ]));

        $dispatchEventId = DB::transaction(function () use ($eventKey, $normalized, $payload): ?int {
            $event = PaymentWebhookEvent::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'merchant_order_id' => $normalized['merchant_order_id'],
                    'payload' => $payload,
                ]
            );

            if ($event->processed_at) {
                return null;
            }

            $event->failed_at = null;
            $event->failure_reason = null;
            $event->save();

            return $event->id;
        });

        if ($dispatchEventId !== null) {
            ProcessPaymentWebhook::dispatch($dispatchEventId)->afterCommit();
        }

        return response()->json(['received' => true]);
    }
}
