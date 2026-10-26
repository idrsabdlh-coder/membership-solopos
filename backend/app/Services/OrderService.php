<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function quote(Plan $plan, ?string $voucherCode): array
    {
        $this->expirePendingOrders();

        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['Paket ini tidak tersedia untuk dibeli.'],
            ]);
        }

        $voucher = $voucherCode ? $this->findUsableVoucher($voucherCode) : null;
        $discount = $voucher ? $this->calculateDiscount($plan->price, $voucher) : 0;

        return [
            'subtotal' => $plan->price,
            'discount' => $discount,
            'total' => $plan->price - $discount,
            'voucher' => $voucher ? [
                'code' => $voucher->code,
                'type' => $voucher->type,
                'value' => $voucher->value,
            ] : null,
        ];
    }

    public function create(User $user, int $planId, ?string $voucherCode): Order
    {
        $this->expirePendingOrders();

        return DB::transaction(function () use ($user, $planId, $voucherCode): Order {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $hasPendingUpgrade = Order::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('upgrade_from_subscription_id')
                ->where(function ($query): void {
                    $query->where(function ($pendingOrder): void {
                        $pendingOrder->where('status', OrderStatus::Pending)
                            ->where('expires_at', '>', now());
                    })->orWhereHas('payments', fn ($payments) => $payments->where('status', 'pending'));
                })
                ->exists();

            if ($hasPendingUpgrade) {
                abort(409, 'Selesaikan atau tunggu kedaluwarsa pesanan upgrade yang masih tertunda.');
            }

            $plan = Plan::query()->whereKey($planId)->lockForUpdate()->first();

            if (! $plan || ! $plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => ['Paket ini tidak tersedia untuk dibeli.'],
                ]);
            }

            $voucher = $voucherCode ? $this->findUsableVoucher($voucherCode, lock: true) : null;
            $discount = $voucher ? $this->calculateDiscount($plan->price, $voucher) : 0;

            $order = Order::query()->create([
                'order_number' => 'ORD-'.Str::upper((string) Str::ulid()),
                'user_id' => $user->getKey(),
                'plan_id' => $plan->getKey(),
                'voucher_id' => $voucher?->getKey(),
                'subtotal' => $plan->price,
                'discount' => $discount,
                'total' => $plan->price - $discount,
                'status' => OrderStatus::Pending,
                'expires_at' => now()->addHours(24),
            ]);

            if ($voucher) {
                $voucher->increment('used');
            }

            return $order->load(['plan', 'voucher']);
        }, attempts: 3);
    }

    public function expirePendingOrders(?int $orderId = null): int
    {
        $query = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<=', now())
            ->when($orderId, fn ($orders) => $orders->whereKey($orderId))
            ->orderBy('id');
        $expiredCount = 0;

        $query->chunkById(100, function (EloquentCollection $orders) use (&$expiredCount): void {
            DB::transaction(function () use ($orders, &$expiredCount): void {
                $lockedOrders = Order::query()
                    ->whereIn('id', $orders->modelKeys())
                    ->where('status', OrderStatus::Pending)
                    ->where('expires_at', '<=', now())
                    ->lockForUpdate()
                    ->get();

                if ($lockedOrders->isEmpty()) {
                    return;
                }

                $voucherReservations = $lockedOrders
                    ->whereNotNull('voucher_id')
                    ->groupBy('voucher_id')
                    ->map(fn ($voucherOrders): int => $voucherOrders->count());

                foreach ($voucherReservations as $voucherId => $count) {
                    $voucher = Voucher::query()->whereKey($voucherId)->lockForUpdate()->first();

                    if ($voucher) {
                        $voucher->decrement('used', min($voucher->used, $count));
                    }
                }

                Order::query()->whereIn('id', $lockedOrders->modelKeys())
                    ->update([
                        'status' => OrderStatus::Expired->value,
                        'updated_at' => now(),
                    ]);

                $expiredCount += $lockedOrders->count();
            }, attempts: 3);
        });

        return $expiredCount;
    }

    public function findUsableVoucher(string $code, bool $lock = false): Voucher
    {
        $query = Voucher::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower(trim($code))]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $voucher = $query->first();

        if (! $voucher
            || ! in_array($voucher->type, ['percent', 'fixed'], true)
            || $voucher->value < 1
            || ($voucher->type === 'percent' && $voucher->value > 100)
            || ($voucher->quota !== null && $voucher->used >= $voucher->quota)
            || ($voucher->valid_from !== null && $voucher->valid_from->isFuture())
            || ($voucher->valid_until !== null && $voucher->valid_until->isPast())) {
            throw ValidationException::withMessages([
                'voucher_code' => ['Kode voucher tidak valid, belum berlaku, sudah kedaluwarsa, atau kuotanya habis.'],
            ]);
        }

        return $voucher;
    }

    private function calculateDiscount(int $subtotal, Voucher $voucher): int
    {
        $discount = $voucher->type === 'percent'
            ? intdiv($subtotal * $voucher->value, 100)
            : $voucher->value;

        return min($subtotal, $discount);
    }
}
