<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleService
{
    public function activateFromPayment(Order $order, Payment $payment): Subscription
    {
        return DB::transaction(function () use ($order, $payment): Subscription {
            $lockedOrder = Order::query()
                ->with('plan')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder->user()->lockForUpdate()->firstOrFail();

            $existing = Subscription::query()
                ->where('source_order_id', $lockedOrder->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($lockedOrder->upgrade_from_subscription_id !== null) {
                $startsAt = now();
                $replacedSubscription = Subscription::query()
                    ->whereKey($lockedOrder->upgrade_from_subscription_id)
                    ->where('user_id', $lockedOrder->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $replacedSubscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'end_date' => $startsAt,
                ]);

                $endsAt = $startsAt->copy()->addDays($lockedOrder->plan->duration_days);

                return Subscription::query()->create([
                    'user_id' => $lockedOrder->user_id,
                    'plan_id' => $lockedOrder->plan_id,
                    'source_order_id' => $lockedOrder->id,
                    'status' => $endsAt->isFuture()
                        ? SubscriptionStatus::Active
                        : SubscriptionStatus::Expired,
                    'start_date' => $startsAt,
                    'end_date' => $endsAt,
                ]);
            }

            $startsAt = $payment->paid_at ?? now();
            $previousSubscription = Subscription::query()
                ->where('user_id', $lockedOrder->user_id)
                ->where('status', '!=', SubscriptionStatus::Cancelled)
                ->where('end_date', '>', $startsAt)
                ->orderByDesc('end_date')
                ->first();

            if ($previousSubscription) {
                $startsAt = $previousSubscription->end_date;
            }

            $endsAt = $startsAt->copy()->addDays($lockedOrder->plan->duration_days);
            $status = $startsAt->isFuture()
                ? SubscriptionStatus::Pending
                : ($endsAt->isFuture() ? SubscriptionStatus::Active : SubscriptionStatus::Expired);

            return Subscription::query()->create([
                'user_id' => $lockedOrder->user_id,
                'plan_id' => $lockedOrder->plan_id,
                'source_order_id' => $lockedOrder->id,
                'status' => $status,
                'start_date' => $startsAt,
                'end_date' => $endsAt,
            ]);
        }, attempts: 3);
    }

    public function transitionStatuses(?int $userId = null): int
    {
        $now = now();

        $expired = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])
            ->where('end_date', '<=', $now)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->update([
                'status' => SubscriptionStatus::Expired->value,
                'updated_at' => $now,
            ]);

        $activated = Subscription::query()
            ->where('status', SubscriptionStatus::Pending)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>', $now)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->update([
                'status' => SubscriptionStatus::Active->value,
                'updated_at' => $now,
            ]);

        return $expired + $activated;
    }
}
