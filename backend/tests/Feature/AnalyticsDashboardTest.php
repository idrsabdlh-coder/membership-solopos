<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_client_events_are_allowlisted_idempotent_and_store_only_approved_properties(): void
    {
        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'name' => 'view_pricing',
            'surface' => 'pricing',
            'email' => 'private@example.test',
        ];

        $this->postJson('/api/analytics/events', $payload)->assertAccepted();
        $this->postJson('/api/analytics/events', $payload)->assertAccepted();

        $this->assertDatabaseCount('analytics_events', 1);
        $event = AnalyticsEvent::query()->sole();
        $this->assertSame('view_pricing', $event->name);
        $this->assertSame(['surface' => 'pricing'], $event->properties);
        $this->assertStringNotContainsString('private@example.test', json_encode($event->getAttributes()));
    }

    public function test_client_cannot_submit_unapproved_event_names_or_properties(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_id' => (string) Str::uuid(),
            'name' => 'payment_success',
        ])->assertUnprocessable();

        $this->postJson('/api/analytics/events', [
            'event_id' => (string) Str::uuid(),
            'name' => 'select_plan',
            'plan_id' => 999,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_dashboard_is_staff_only_and_returns_scoped_event_counts(): void
    {
        AnalyticsEvent::query()->create([
            'event_key' => 'client:pricing-1',
            'name' => 'view_pricing',
            'properties' => ['surface' => 'pricing'],
            'occurred_at' => now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_key' => 'client:pricing-2',
            'name' => 'view_pricing',
            'properties' => null,
            'occurred_at' => now()->subDays(40),
        ]);

        $member = User::factory()->create();
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/analytics/dashboard')
            ->assertForbidden();

        $staff = User::factory()->create();
        $staff->forceFill(['role' => UserRole::Staff])->save();
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('data.events.view_pricing', 1)
            ->assertJsonPath('data.period.timezone', 'Asia/Jakarta')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_dashboard_rejects_ranges_longer_than_366_days(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['role' => UserRole::Staff])->save();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/analytics/dashboard?from=2025-01-01&to=2026-01-02')
            ->assertUnprocessable();
    }

    public function test_dashboard_calculates_subscription_order_and_payment_metrics(): void
    {
        $plan = Plan::query()
            ->where('type', 'free_ads_epaper')
            ->where('billing_period', 'monthly')
            ->firstOrFail();
        $activeUser = User::factory()->create();
        $this->createSubscription(
            $activeUser,
            $plan,
            now()->subDays(40),
            now()->addDays(20),
            SubscriptionStatus::Active
        );

        $churnedUser = User::factory()->create();
        $this->createSubscription($churnedUser, $plan, now()->subDays(60), now()->subDays(3), SubscriptionStatus::Expired);

        $renewedUser = User::factory()->create();
        $expiredTerm = $this->createSubscription(
            $renewedUser,
            $plan,
            now()->subDays(40),
            now()->subDays(3),
            SubscriptionStatus::Expired
        );
        $this->createSubscription(
            $renewedUser,
            $plan,
            $expiredTerm->end_date,
            now()->addDays(27),
            SubscriptionStatus::Active
        );

        $paidOrder = $this->createOrder($activeUser, $plan, OrderStatus::Paid);
        $this->createPayment($paidOrder, PaymentStatus::Success);
        $this->createOrder(User::factory()->create(), $plan, OrderStatus::Pending);

        $staff = User::factory()->create();
        $staff->forceFill(['role' => UserRole::Staff])->save();
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('data.customers.active', 2)
            ->assertJsonPath('data.customers.at_period_start', 3)
            ->assertJsonPath('data.customers.churned', 1)
            ->assertJsonPath('data.customers.churn_rate_percent', 33.33)
            ->assertJsonPath('data.mrr_idr', 60000)
            ->assertJsonPath('data.orders_by_plan.0.orders', 2)
            ->assertJsonPath('data.orders_by_plan.0.paid_orders', 1)
            ->assertJsonPath('data.orders_by_plan.0.conversion_rate_percent', 50)
            ->assertJsonPath('data.popular_payment_methods.0.method', 'qris')
            ->assertJsonPath('data.popular_payment_methods.0.payments', 1);
    }

    private function createSubscription(
        User $user,
        Plan $plan,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        SubscriptionStatus $status
    ): Subscription {
        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function createOrder(User $user, Plan $plan, OrderStatus $status): Order
    {
        return Order::query()->create([
            'order_number' => 'ANALYTICS-'.strtoupper((string) Str::ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => $status,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function createPayment(Order $order, PaymentStatus $status): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => $status,
            'paid_at' => now(),
        ]);
    }
}
