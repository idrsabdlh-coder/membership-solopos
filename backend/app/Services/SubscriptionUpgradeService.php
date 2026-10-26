<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly SubscriptionLifecycleService $subscriptions,
    ) {}

    /**
     * @return array{
     *     current_subscription: Subscription,
     *     target_plan: Plan,
     *     current_plan_price: int,
     *     subtotal: int,
     *     upgrade_credit: int,
     *     total: int,
     *     remaining_seconds: int,
     *     total_term_seconds: int
     * }
     */
    public function quote(User $user, int $targetPlanId): array
    {
        $this->subscriptions->transitionStatuses((int) $user->getKey());
        $now = now();
        $current = $this->currentSubscription((int) $user->getKey());

        $this->assertNoScheduledSubscription((int) $user->getKey(), $now);
        $this->assertNoPendingOrders($user, $now);

        $target = Plan::query()->findOrFail($targetPlanId);

        return $this->calculateQuote($current, $target, $now);
    }

    public function create(User $user, int $targetPlanId): Order
    {
        $this->orders->expirePendingOrders();
        $this->subscriptions->transitionStatuses((int) $user->getKey());

        return DB::transaction(function () use ($user, $targetPlanId): Order {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $now = now();
            $current = $this->currentSubscription((int) $user->getKey(), lock: true);

            $this->assertNoScheduledSubscription((int) $user->getKey(), $now);
            $this->assertNoPendingOrders($user, $now);

            $target = Plan::query()->whereKey($targetPlanId)->lockForUpdate()->firstOrFail();
            $quote = $this->calculateQuote($current, $target, $now);
            $isZeroAmount = $quote['total'] === 0;

            $order = Order::query()->create([
                'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
                'user_id' => $user->getKey(),
                'plan_id' => $target->getKey(),
                'subtotal' => $quote['subtotal'],
                'discount' => 0,
                'upgrade_credit' => $quote['upgrade_credit'],
                'upgrade_from_subscription_id' => $current->getKey(),
                'total' => $quote['total'],
                'status' => $isZeroAmount ? OrderStatus::Paid : OrderStatus::Pending,
                'expires_at' => $now->copy()->addHours(24),
            ]);

            if ($isZeroAmount) {
                $payment = Payment::query()->create([
                    'order_id' => $order->getKey(),
                    'gateway' => 'internal',
                    'method' => 'upgrade_credit',
                    'amount' => 0,
                    'status' => PaymentStatus::Success,
                    'paid_at' => $now,
                ]);

                OrderPaid::dispatch($order, $payment);
            }

            return $order->load([
                'plan',
                'voucher',
                'payments' => fn ($query) => $query->latest('id'),
            ]);
        }, attempts: 3);
    }

    private function currentSubscription(int $userId, bool $lock = false): Subscription
    {
        $query = Subscription::query()
            ->active()
            ->where('user_id', $userId)
            ->with(['plan', 'sourceOrder'])
            ->orderByDesc('end_date');

        if ($lock) {
            $query->lockForUpdate();
        }

        $subscription = $query->first();

        if (! $subscription) {
            abort(409, 'Langganan aktif diperlukan untuk melakukan upgrade.');
        }

        return $subscription;
    }

    private function assertNoScheduledSubscription(int $userId, Carbon $now): void
    {
        $hasScheduledSubscription = Subscription::query()
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::Pending)
            ->where('start_date', '>', $now)
            ->exists();

        if ($hasScheduledSubscription) {
            abort(409, 'Upgrade belum dapat dilakukan selama ada langganan terjadwal.');
        }
    }

    private function assertNoPendingOrders(User $user, Carbon $now): void
    {
        $hasPendingOrder = Order::query()
            ->where('user_id', $user->getKey())
            ->where(function ($query) use ($now): void {
                $query->where(function ($pendingOrder) use ($now): void {
                    $pendingOrder->where('status', OrderStatus::Pending)
                        ->where('expires_at', '>', $now);
                })->orWhere(function ($upgradeOrder): void {
                    $upgradeOrder->whereNotNull('upgrade_from_subscription_id')
                        ->whereHas('payments', fn ($payments) => $payments->where('status', 'pending'));
                });
            })
            ->exists();

        if ($hasPendingOrder) {
            abort(409, 'Selesaikan atau tunggu kedaluwarsa pesanan yang masih tertunda sebelum upgrade.');
        }
    }

    /**
     * @return array{
     *     current_subscription: Subscription,
     *     target_plan: Plan,
     *     current_plan_price: int,
     *     subtotal: int,
     *     upgrade_credit: int,
     *     total: int,
     *     remaining_seconds: int,
     *     total_term_seconds: int
     * }
     */
    private function calculateQuote(Subscription $current, Plan $target, Carbon $now): array
    {
        if (! $target->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['Paket tujuan tidak tersedia untuk upgrade.'],
            ]);
        }

        $currentPlan = $current->plan;
        $currentPlanPrice = $current->sourceOrder?->subtotal ?? $currentPlan->price;
        $remainingSeconds = max(0, $current->end_date->getTimestamp() - $now->getTimestamp());
        $currentEntitlementsRetained = $target->duration_days * 86400 >= $remainingSeconds
            && (! $currentPlan->includesFreeAds() || $target->includesFreeAds())
            && (! $currentPlan->includesEpaper() || $target->includesEpaper())
            && $target->clipping_quota >= $currentPlan->clipping_quota
            && $target->download_limit_per_edition >= $currentPlan->download_limit_per_edition
            && $target->download_limit_per_day >= $currentPlan->download_limit_per_day;
        $improvesEntitlement = $target->duration_days * 86400 > $remainingSeconds
            || $target->includesFreeAds() !== $currentPlan->includesFreeAds()
            || $target->includesEpaper() !== $currentPlan->includesEpaper()
            || $target->clipping_quota > $currentPlan->clipping_quota
            || $target->download_limit_per_edition > $currentPlan->download_limit_per_edition
            || $target->download_limit_per_day > $currentPlan->download_limit_per_day;
        $totalTermSeconds = $current->end_date->getTimestamp() - $current->start_date->getTimestamp();

        if ($target->getKey() === $currentPlan->getKey()
            || ! $currentEntitlementsRetained
            || ! $improvesEntitlement
            || $totalTermSeconds < 1) {
            throw ValidationException::withMessages([
                'plan_id' => ['Paket tujuan bukan upgrade yang memenuhi aturan hak dan masa aktif.'],
            ]);
        }

        $credit = intdiv($currentPlanPrice * $remainingSeconds, $totalTermSeconds);

        return [
            'current_subscription' => $current,
            'target_plan' => $target,
            'current_plan_price' => $currentPlanPrice,
            'subtotal' => $target->price,
            'upgrade_credit' => $credit,
            'total' => max(0, $target->price - $credit),
            'remaining_seconds' => $remainingSeconds,
            'total_term_seconds' => $totalTermSeconds,
        ];
    }
}
