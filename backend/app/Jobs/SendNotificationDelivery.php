<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\MembershipNoticeMail;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AnalyticsEventService;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60, 300, 600];
    }

    public function __construct(public readonly int $deliveryId) {}

    public function handle(InvoiceService $invoices, AnalyticsEventService $analytics): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => $delivery->attempts + 1,
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        [$heading, $message, $details, $invoicePdf] = $this->content($delivery, $invoices);

        Mail::to($delivery->recipient)->send(new MembershipNoticeMail(
            $heading,
            $message,
            $details,
            $invoicePdf,
            $invoicePdf === null ? null : 'invoice-'.$delivery->context['order_number'].'.pdf',
        ));

        $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'failure_reason' => null,
        ])->save();

        if (str_starts_with($delivery->type, 'subscription.reminder.')) {
            $analytics->recordGenerated('reminder_sent', 'notification_delivery', $delivery->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('status', '!=', 'sent')
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $exception === null ? 'Delivery failed after retries.' : $exception::class,
                'updated_at' => now(),
            ]);

        Log::error('Transactional email delivery failed after retries.', [
            'notification_delivery_id' => $this->deliveryId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }

    /**
     * @return array{string, string, array<string, string|int|null>, ?string}
     */
    private function content(NotificationDelivery $delivery, InvoiceService $invoices): array
    {
        $context = $delivery->context ?? [];

        if (str_starts_with($delivery->type, 'order.')) {
            $order = Order::query()
                ->with(['user', 'plan', 'voucher', 'subscription'])
                ->findOrFail($context['order_id']);
            $order->status = OrderStatus::from($context['order_status']);

            $paymentId = $context['payment_id'] ?? null;
            if ($paymentId !== null) {
                $payment = Payment::query()->findOrFail($paymentId);
                $payment->status = PaymentStatus::from($context['payment_status']);
                $payment->method = $context['payment_method'];
                $payment->paid_at = $context['paid_at'];
                $order->setRelation('payments', collect([$payment]));
            } else {
                $order->setRelation('payments', collect());
            }

            [$heading, $message] = match ($delivery->type) {
                'order.payment_succeeded' => [
                    'Pembayaran berhasil',
                    'Pembayaran Anda berhasil. Invoice transaksi terlampir.',
                ],
                'order.payment_failed' => [
                    'Pembayaran gagal',
                    'Pembayaran untuk pesanan ini gagal. Anda dapat mencoba kembali selama pesanan masih berlaku.',
                ],
                'order.payment_expired' => [
                    'Pembayaran kedaluwarsa',
                    'Batas waktu transaksi pembayaran ini telah berakhir. Periksa status pesanan sebelum mencoba kembali.',
                ],
                default => [
                    'Pesanan kedaluwarsa',
                    'Batas waktu pembayaran pesanan telah berakhir. Invoice dengan status terakhir terlampir.',
                ],
            };

            return [
                $heading,
                $message,
                [
                    'Nomor pesanan' => $context['order_number'],
                    'Paket' => $context['plan_name'],
                    'Jumlah (IDR)' => number_format((int) $context['amount'], 0, ',', '.'),
                    'Status' => $context['payment_status'] ?? $context['order_status'],
                ],
                $invoices->generate($order),
            ];
        }

        $isExpired = $delivery->type === 'subscription.expired';
        $day = match ($delivery->type) {
            'subscription.reminder.h7' => 7,
            'subscription.reminder.h3' => 3,
            default => 1,
        };

        return [
            $isExpired ? 'Langganan Anda telah berakhir' : 'Pengingat masa aktif langganan',
            $isExpired
                ? 'Masa aktif langganan Anda telah berakhir. Silakan pilih paket untuk berlangganan kembali.'
                : "Masa aktif langganan Anda akan berakhir dalam {$day} hari. Perpanjang sebelum masa aktif berakhir.",
            [
                'Paket' => $context['plan_name'],
                'Berakhir pada' => $context['end_date'],
            ],
            null,
        ];
    }
}
