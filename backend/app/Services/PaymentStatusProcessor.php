<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentStatusProcessor
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function processWebhookEvent(int $eventId): void
    {
        $event = PaymentWebhookEvent::query()->findOrFail($eventId);
        $this->process($event->payload, $event);
    }

    public function processStatusResponse(array $payload): void
    {
        $this->process($payload);
    }

    private function process(array $payload, ?PaymentWebhookEvent $webhookEvent = null): void
    {
        $notification = $this->gateway->normalizeNotification($payload);
        $orderId = $notification['merchant_order_id'] ?? null;
        $transactionId = $notification['transaction_id'] ?? null;

        if (! is_string($orderId) || ! is_string($transactionId) || $transactionId === '') {
            throw new RuntimeException('Payment notification is missing a transaction reference.');
        }

        $orderPaid = null;

        DB::transaction(function () use ($orderId, $transactionId, $notification, $payload, $webhookEvent, &$orderPaid): void {
            if ($webhookEvent) {
                $webhookEvent = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($webhookEvent->id);
                if ($webhookEvent->processed_at) {
                    return;
                }
            }

            $payment = Payment::query()
                ->where('gateway', 'midtrans')
                ->where('merchant_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('No payment matches the Midtrans merchant order reference.');
            }

            if (($notification['currency'] ?? 'IDR') !== 'IDR'
                || ! is_int($notification['amount'] ?? null)
                || $notification['amount'] !== $payment->amount) {
                throw new RuntimeException('Payment notification amount or currency does not match the order.');
            }

            $status = $notification['status'] ?? null;
            if ($status !== null && ! $status instanceof PaymentStatus) {
                throw new RuntimeException('Payment notification status is invalid.');
            }

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $wasExpired = $order->status === OrderStatus::Expired;
            $wasPaid = $order->status === OrderStatus::Paid;

            if ($payment->gateway_ref && ! hash_equals($payment->gateway_ref, $transactionId)) {
                throw new RuntimeException('Midtrans transaction reference changed for an existing payment attempt.');
            }

            $payment->gateway_ref = $transactionId;
            $payment->method = is_string($notification['method'] ?? null)
                ? $notification['method']
                : $payment->method;
            $payment->raw_payload = $payload;

            if ($status === PaymentStatus::Success) {
                $payment->status = PaymentStatus::Success;
                $payment->paid_at = $this->parsePaidAt($notification['paid_at'] ?? null);
                $order->status = OrderStatus::Paid;

                if ($wasExpired && $order->voucher_id) {
                    Voucher::query()->whereKey($order->voucher_id)->increment('used');
                }

                if (! $wasPaid) {
                    $orderPaid = [$order, $payment];
                }
            } elseif ($status !== null && $payment->status !== PaymentStatus::Success) {
                $payment->status = $status;
            }

            $payment->save();
            $order->save();

            if ($webhookEvent) {
                $webhookEvent->payment_id = $payment->id;
                $webhookEvent->processed_at = now();
                $webhookEvent->failed_at = null;
                $webhookEvent->failure_reason = null;
                $webhookEvent->save();
            }

            if ($orderPaid) {
                OrderPaid::dispatch($orderPaid[0], $orderPaid[1]);
                $orderPaid = null;
            }
        }, attempts: 3);
    }

    private function parsePaidAt(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return now();
        }

        try {
            return now()->parse($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'payment' => ['Waktu pembayaran dari gateway tidak valid.'],
            ]);
        }
    }
}
