<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentStatusProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MemberHistoryUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'sandbox-server-key',
            'services.midtrans.client_key' => 'sandbox-client-key',
            'services.midtrans.is_production' => false,
        ]);
        $this->seed(PlanSeeder::class);
    }

    public function test_upgrade_quote_and_order_use_server_calculated_prorata_credit(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        $targetPlan = $this->plan('free_ads_epaper');
        $sourceOrder = $this->createOrder($user, $currentPlan, OrderStatus::Paid);
        Order::query()->whereKey($sourceOrder->id)->update(['subtotal' => 18000, 'total' => 18000]);
        $sourceOrder->refresh();
        $this->createActiveSubscription($user, $currentPlan, elapsedDays: 15, remainingDays: 15, sourceOrder: $sourceOrder);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $targetPlan->id])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 30000)
            ->assertJsonPath('data.current_plan_price', 18000)
            ->assertJsonPath('data.upgrade_credit', 9000)
            ->assertJsonPath('data.total', 21000)
            ->assertJsonPath('data.total_term_seconds', 2592000)
            ->assertJsonPath('data.target_plan.id', $targetPlan->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade', [
                'plan_id' => $targetPlan->id,
                'upgrade_credit' => 1,
                'voucher_code' => 'NOT-ALLOWED',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['upgrade_credit', 'voucher_code']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade', ['plan_id' => $targetPlan->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'upgrade')
            ->assertJsonPath('data.upgrade_credit', 9000)
            ->assertJsonPath('data.total', 21000)
            ->assertJsonPath('data.status', 'pending');

        $order = Order::query()->where('order_number', $response->json('data.order_number'))->firstOrFail();
        $this->assertSame(0, $order->discount);
        $this->assertSame($currentPlan->id, $order->upgradeFromSubscription()->value('plan_id'));
        $this->assertDatabaseCount('orders', 2);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', ['plan_id' => $targetPlan->id])
            ->assertConflict();
    }

    public function test_successful_upgrade_payment_replaces_current_subscription_immediately(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        Queue::fake();
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        $targetPlan = $this->plan('free_ads_epaper');
        $current = $this->createActiveSubscription($user, $currentPlan, elapsedDays: 15, remainingDays: 15);
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade', ['plan_id' => $targetPlan->id])
            ->assertCreated();
        $order = Order::query()->where('order_number', $response->json('data.order_number'))->firstOrFail();
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        $this->postJson('/api/payments/webhook', $this->signedSettlement($payment))->assertOk();
        Queue::assertPushed(ProcessPaymentWebhook::class);
        $event = PaymentWebhookEvent::query()->firstOrFail();
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);

        $activated = Subscription::query()->where('source_order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(SubscriptionStatus::Cancelled, $current->fresh()->status);
        $this->assertTrue($current->fresh()->end_date->equalTo(now()));
        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertSame($targetPlan->id, $activated->plan_id);
        $this->assertTrue($activated->start_date->equalTo(now()));
        $this->assertTrue($activated->end_date->equalTo(now()->addDays(30)));
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
    }

    public function test_upgrade_fully_covered_by_credit_is_completed_without_gateway_payment(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        Plan::query()->whereKey($currentPlan->id)->update(['price' => 60000]);
        $currentPlan->refresh();
        $targetPlan = $this->plan('free_ads_epaper');
        $current = $this->createActiveSubscription($user, $currentPlan, elapsedDays: 10, remainingDays: 20);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade', ['plan_id' => $targetPlan->id])
            ->assertCreated()
            ->assertJsonPath('data.upgrade_credit', 40000)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment.gateway', 'internal')
            ->assertJsonPath('data.payment.status', 'success');

        $order = Order::query()->where('order_number', $response->json('data.order_number'))->firstOrFail();
        $activated = Subscription::query()->where('source_order_id', $order->id)->firstOrFail();
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(0, $order->payments()->firstOrFail()->amount);
        $this->assertSame(SubscriptionStatus::Cancelled, $current->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertSame($targetPlan->id, $activated->plan_id);
    }

    public function test_quote_returns_422_when_target_plan_is_incomparable(): void
    {
        $user = User::factory()->create();
        $currentPlan = $this->plan('epaper');
        $this->createActiveSubscription($user, $currentPlan, elapsedDays: 5, remainingDays: 25);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $this->plan('free_ads')->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id')
            ->assertJsonPath('errors.plan_id.0', 'Paket tujuan bukan upgrade yang memenuhi aturan hak dan masa aktif.');
    }

    public function test_quote_returns_422_when_target_plan_does_not_improve_entitlements(): void
    {
        $user = User::factory()->create();
        $currentPlan = $this->plan('epaper');
        $this->createActiveSubscription($user, $currentPlan, elapsedDays: 5, remainingDays: 25);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $currentPlan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id')
            ->assertJsonPath('errors.plan_id.0', 'Paket tujuan bukan upgrade yang memenuhi aturan hak dan masa aktif.');
    }

    public function test_quote_returns_409_when_a_scheduled_renewal_exists(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        $this->createActiveSubscription($user, $currentPlan, elapsedDays: 10, remainingDays: 20);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $currentPlan->id,
            'status' => SubscriptionStatus::Pending,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(50),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $this->plan('free_ads_epaper')->id])
            ->assertConflict();
    }

    public function test_pending_gateway_payment_for_expired_upgrade_returns_409_for_checkout_and_upgrade(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        $current = $this->createActiveSubscription($user, $currentPlan, elapsedDays: 10, remainingDays: 20);
        $targetPlan = $this->plan('free_ads_epaper');
        $order = Order::query()->create([
            'order_number' => 'ORD-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $targetPlan->id,
            'subtotal' => $targetPlan->price,
            'discount' => 0,
            'upgrade_credit' => 5000,
            'upgrade_from_subscription_id' => $current->id,
            'total' => $targetPlan->price - 5000,
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $targetPlan->id])
            ->assertConflict();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', ['plan_id' => $targetPlan->id])
            ->assertConflict();
    }

    public function test_status_check_reconciles_expired_upgrade_payment_and_releases_order_lock(): void
    {
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $currentPlan = $this->plan('free_ads');
        $current = $this->createActiveSubscription($user, $currentPlan, elapsedDays: 10, remainingDays: 20);
        $targetPlan = $this->plan('free_ads_epaper');
        $order = Order::query()->create([
            'order_number' => 'ORD-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $targetPlan->id,
            'subtotal' => $targetPlan->price,
            'discount' => 0,
            'upgrade_credit' => 5000,
            'upgrade_from_subscription_id' => $current->id,
            'total' => $targetPlan->price - 5000,
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $payment->merchant_order_id,
                'transaction_id' => 'upgrade-expired-transaction-'.$payment->id,
                'transaction_status' => 'expire',
                'status_code' => '202',
                'gross_amount' => number_format($payment->amount, 2, '.', ''),
                'payment_type' => 'qris',
                'currency' => 'IDR',
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'expired')
            ->assertJsonPath('data.payment.status', 'expired');

        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $targetPlan->id])
            ->assertOk();
    }

    public function test_history_and_upgrade_requests_return_401_without_authentication(): void
    {
        $targetPlan = $this->plan('free_ads_epaper');

        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/1/invoice')->assertUnauthorized();
        $this->getJson('/api/subscriptions/history')->assertUnauthorized();
        $this->postJson('/api/subscription/upgrade/quote', ['plan_id' => $targetPlan->id])->assertUnauthorized();
        $this->postJson('/api/subscription/upgrade', ['plan_id' => $targetPlan->id])->assertUnauthorized();
    }

    public function test_upgrade_quote_returns_409_without_an_active_subscription(): void
    {
        $targetPlan = $this->plan('free_ads_epaper');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscription/upgrade/quote', ['plan_id' => $targetPlan->id])
            ->assertConflict();
    }

    public function test_transaction_and_subscription_history_are_owner_scoped_and_invoice_is_a_pdf(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = $this->plan('free_ads_epaper');
        $order = $this->createOrder($user, $plan, OrderStatus::Paid);
        Order::query()->whereKey($order->id)->update(['subtotal' => 28000, 'total' => 28000]);
        $order->refresh();
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Success,
            'paid_at' => now(),
        ]);
        $subscription = $this->createActiveSubscription($user, $plan, 5, 25, $order);
        $otherOrder = $this->createOrder($otherUser, $plan, OrderStatus::Paid);
        $this->createActiveSubscription($otherUser, $plan, 5, 25, $otherOrder);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $order->order_number)
            ->assertJsonPath('data.0.payment_status', 'success')
            ->assertJsonPath('data.0.period.status', 'active');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $subscription->id)
            ->assertJsonPath('data.0.plan.price', 28000)
            ->assertJsonPath('data.0.transaction.payment_status', 'success');

        $this->actingAs($user, 'sanctum')
            ->get('/api/orders/'.$otherOrder->id.'/invoice')
            ->assertNotFound();

        $invoice = $this->actingAs($user, 'sanctum')
            ->get('/api/orders/'.$order->id.'/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $invoice->streamedContent());
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
    }

    private function plan(string $type): Plan
    {
        return Plan::query()
            ->where('type', $type)
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    private function createActiveSubscription(
        User $user,
        Plan $plan,
        int $elapsedDays,
        int $remainingDays,
        ?Order $sourceOrder = null,
    ): Subscription {
        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'source_order_id' => $sourceOrder?->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays($elapsedDays),
            'end_date' => now()->addDays($remainingDays),
        ]);
    }

    private function createOrder(User $user, Plan $plan, OrderStatus $status): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => $status,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function signedSettlement(Payment $payment): array
    {
        $grossAmount = number_format($payment->amount, 2, '.', '');
        $statusCode = '200';

        return [
            'order_id' => $payment->merchant_order_id,
            'transaction_id' => 'upgrade-transaction-'.$payment->id,
            'transaction_status' => 'settlement',
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'payment_type' => 'qris',
            'settlement_time' => now()->format('Y-m-d H:i:s'),
            'currency' => 'IDR',
            'signature_key' => hash(
                'sha512',
                $payment->merchant_order_id.$statusCode.$grossAmount.config('services.midtrans.server_key')
            ),
        ];
    }
}
