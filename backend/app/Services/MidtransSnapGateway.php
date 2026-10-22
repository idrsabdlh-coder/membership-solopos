<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MidtransSnapGateway implements PaymentGatewayInterface
{
    public function createTransaction(Order $order, Payment $payment): array
    {
        try {
            $response = $this->client()->post($this->baseUrl().'/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $payment->merchant_order_id,
                    'gross_amount' => $payment->amount,
                ],
                'item_details' => [[
                    'id' => (string) $order->plan_id,
                    'price' => $payment->amount,
                    'quantity' => 1,
                    'name' => Str::limit($order->plan->name, 50, ''),
                ]],
                'customer_details' => array_filter([
                    'first_name' => Str::limit($order->user->name, 50, ''),
                    'email' => $order->user->email,
                    'phone' => $order->user->whatsapp,
                ], static fn ($value): bool => $value !== null && $value !== ''),
                'enabled_payments' => $this->enabledPayments($payment->method),
                'expiry' => [
                    'start_time' => now()->format('Y-m-d H:i:s O'),
                    'unit' => 'minute',
                    'duration' => max(1, (int) now()->diffInMinutes($order->expires_at)),
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Midtrans Snap could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Midtrans Snap transaction request failed with HTTP '.$response->status().'.');
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['token'], $data['redirect_url'])) {
            throw new RuntimeException('Midtrans Snap returned an invalid transaction response.');
        }

        return [
            'snap_token' => $data['token'],
            'redirect_url' => $data['redirect_url'],
        ];
    }

    public function fetchStatus(string $merchantOrderId): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/v2/'.rawurlencode($merchantOrderId).'/status');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Midtrans status service could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Midtrans status request failed with HTTP '.$response->status().'.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Midtrans returned an invalid transaction status response.');
        }

        return $data;
    }

    public function verifyNotification(array $notification): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $orderId = $notification['order_id'] ?? null;
        $statusCode = $notification['status_code'] ?? null;
        $grossAmount = $notification['gross_amount'] ?? null;
        $signature = $notification['signature_key'] ?? null;

        if ($serverKey === '' || ! is_string($orderId) || ! is_string($statusCode)
            || ! is_string($grossAmount) || ! is_string($signature)) {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    public function normalizeNotification(array $notification): array
    {
        $transactionStatus = $notification['transaction_status'] ?? null;
        $fraudStatus = $notification['fraud_status'] ?? null;

        $status = match (true) {
            $transactionStatus === 'settlement' => PaymentStatus::Success,
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => PaymentStatus::Success,
            $transactionStatus === 'pending', $transactionStatus === 'authorize' => PaymentStatus::Pending,
            $transactionStatus === 'expire' => PaymentStatus::Expired,
            in_array($transactionStatus, ['deny', 'cancel', 'failure'], true) => PaymentStatus::Failed,
            default => null,
        };

        return [
            'merchant_order_id' => $notification['order_id'] ?? null,
            'transaction_id' => $notification['transaction_id'] ?? null,
            'amount' => $this->parseAmount($notification['gross_amount'] ?? null),
            'status' => $status,
            'method' => $notification['payment_type'] ?? null,
            'paid_at' => $notification['settlement_time']
                ?? $notification['transaction_time']
                ?? null,
            'currency' => $notification['currency'] ?? 'IDR',
        ];
    }

    private function client()
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '') {
            throw new RuntimeException('Midtrans server key is not configured.');
        }

        return Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function enabledPayments(string $method): array
    {
        return match ($method) {
            'bank_transfer' => ['bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va'],
            'qris' => ['qris'],
            'gopay' => ['gopay'],
            'shopeepay' => ['shopeepay'],
            default => throw new RuntimeException('Unsupported Midtrans payment method.'),
        };
    }

    private function parseAmount(mixed $amount): ?int
    {
        if (! is_string($amount) || preg_match('/^\d+(?:\.00)?$/', $amount) !== 1) {
            return null;
        }

        return (int) $amount;
    }
}
