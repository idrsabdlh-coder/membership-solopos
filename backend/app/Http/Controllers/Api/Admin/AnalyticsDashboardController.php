<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalyticsDashboardController extends Controller
{
    public function show(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $fromDate = CarbonImmutable::parse($validated['from'] ?? $today->subDays(29)->toDateString(), 'Asia/Jakarta');
        $toDate = CarbonImmutable::parse($validated['to'] ?? $today->toDateString(), 'Asia/Jakarta');
        if ($fromDate->diffInDays($toDate) > 365) {
            throw ValidationException::withMessages([
                'to' => ['Rentang laporan maksimal 366 hari.'],
            ]);
        }

        $from = $fromDate->startOfDay()->utc();
        $to = $toDate->endOfDay()->utc();
        $activeAt = now();
        $activeSubscriptions = Subscription::query()
            ->with('plan:id,name,price,duration_days')
            ->where('status', SubscriptionStatus::Active)
            ->where('start_date', '<=', $activeAt)
            ->where('end_date', '>', $activeAt)
            ->get();
        $activeCustomers = $activeSubscriptions->pluck('user_id')->unique()->count();
        $monthlyRecurringRevenue = $activeSubscriptions->sum(
            fn (Subscription $subscription): int => intdiv(
                (int) $subscription->plan->price * 30,
                max(1, (int) $subscription->plan->duration_days)
            )
        );

        $periodStartCustomers = Subscription::query()
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->where('start_date', '<=', $from)
            ->where('end_date', '>', $from)
            ->select('user_id')
            ->distinct();
        $churnedCustomers = Subscription::query()
            ->where('status', SubscriptionStatus::Expired)
            ->whereBetween('end_date', [$from, $to])
            ->whereIn('user_id', $periodStartCustomers)
            ->whereNotExists(function ($query) use ($to): void {
                $query->selectRaw('1')
                    ->from('subscriptions as renewal')
                    ->whereColumn('renewal.user_id', 'subscriptions.user_id')
                    ->whereColumn('renewal.id', '!=', 'subscriptions.id')
                    ->whereColumn('renewal.end_date', '>', 'subscriptions.end_date')
                    ->where('renewal.start_date', '<=', $to);
            })
            ->distinct()
            ->count('user_id');
        $periodStartCustomerCount = (clone $periodStartCustomers)->distinct()->count('user_id');

        $ordersByPlan = Order::query()
            ->join('plans', 'plans.id', '=', 'orders.plan_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('plans.id', 'plans.name')
            ->orderBy('plans.name')
            ->select([
                'plans.id as plan_id',
                'plans.name as plan_name',
                DB::raw('COUNT(orders.id) as orders'),
            ])
            ->selectRaw(
                'SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as paid_orders',
                [OrderStatus::Paid->value]
            )
            ->get()
            ->map(fn (object $row): array => [
                'plan_id' => (int) $row->plan_id,
                'plan_name' => $row->plan_name,
                'orders' => (int) $row->orders,
                'paid_orders' => (int) $row->paid_orders,
                'conversion_rate_percent' => $row->orders > 0
                    ? round(((int) $row->paid_orders / (int) $row->orders) * 100, 2)
                    : 0,
            ]);

        $popularPaymentMethods = Payment::query()
            ->where('status', 'success')
            ->whereBetween('paid_at', [$from, $to])
            ->select('method', DB::raw('COUNT(*) as payments'))
            ->groupBy('method')
            ->orderByDesc('payments')
            ->orderBy('method')
            ->get()
            ->map(fn (object $row): array => [
                'method' => $row->method,
                'payments' => (int) $row->payments,
            ]);

        $eventCounts = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->select('name', DB::raw('COUNT(*) as events'))
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->name => (int) $row->events]);

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'analytics.dashboard.viewed', 'analytics_dashboard', null, [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ]);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                    'timezone' => 'Asia/Jakarta',
                ],
                'as_of' => $activeAt->timezone('Asia/Jakarta')->toIso8601String(),
                'customers' => [
                    'active' => $activeCustomers,
                    'at_period_start' => $periodStartCustomerCount,
                    'churned' => $churnedCustomers,
                    'churn_rate_percent' => $periodStartCustomerCount > 0
                        ? round(($churnedCustomers / $periodStartCustomerCount) * 100, 2)
                        : 0,
                ],
                'mrr_idr' => $monthlyRecurringRevenue,
                'orders_by_plan' => $ordersByPlan,
                'popular_payment_methods' => $popularPaymentMethods,
                'events' => $eventCounts,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
