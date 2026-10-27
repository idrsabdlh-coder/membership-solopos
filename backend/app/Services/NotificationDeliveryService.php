<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class NotificationDeliveryService
{
    public function queuePaymentOutcome(Order $order, Payment $payment): void
    {
        $type = match ($payment->status) {
            PaymentStatus::Success => 'order.payment_succeeded',
            PaymentStatus::Failed => 'order.payment_failed',
            PaymentStatus::Expired => 'order.payment_expired',
            default => null,
        };

        if ($type === null) {
            return;
        }

        $this->queue(
            $order->user_id,
            $order->user->email,
            $type,
            'payment',
            $payment->id,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->status->value,
                'plan_name' => $order->plan->name,
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
                'payment_status' => $payment->status->value,
                'payment_method' => $payment->method,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
        );
    }

    public function queueOrderExpired(Order $order): void
    {
        $this->queue(
            $order->user_id,
            $order->user->email,
            'order.expired',
            'order',
            $order->id,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => OrderStatus::Expired->value,
                'plan_name' => $order->plan->name,
                'amount' => $order->total,
                'payment_id' => $order->payments->first()?->id,
                'payment_status' => $order->payments->first()?->status->value,
                'payment_method' => $order->payments->first()?->method,
                'paid_at' => $order->payments->first()?->paid_at?->toIso8601String(),
            ],
        );
    }

    public function queueSubscriptionNotice(Subscription $subscription, string $type): void
    {
        $this->queue(
            $subscription->user_id,
            $subscription->user->email,
            $type,
            'subscription',
            $subscription->id,
            [
                'plan_name' => $subscription->plan->name,
                'end_date' => $subscription->end_date->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB',
            ],
        );
    }

    /**
     * @param  array<string, string|int|null>  $context
     */
    private function queue(
        int $userId,
        string $recipient,
        string $type,
        string $sourceType,
        int $sourceId,
        array $context,
    ): void {
        $now = now();
        $inserted = DB::table('notification_deliveries')->insertOrIgnore([
            'user_id' => $userId,
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'recipient' => $recipient,
            'status' => 'queued',
            'attempts' => 0,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return;
        }

        $delivery = NotificationDelivery::query()
            ->where('type', $type)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->firstOrFail();

        SendNotificationDelivery::dispatch($delivery->id)->afterCommit();
    }
}
