<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentStatusProcessor;
use App\Services\SubscriptionLifecycleService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
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

    public function test_successful_payment_automatically_activates_a_subscription(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedSettlement($payment);

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);

        $subscription = Subscription::query()->firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($order->id, $subscription->source_order_id);
        $this->assertSame($order->plan->duration_days, (int) $subscription->start_date->diffInDays($subscription->end_date));
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_repeated_payment_processing_does_not_create_duplicate_subscriptions(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedSettlement($payment);

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();
        $processor = app(PaymentStatusProcessor::class);

        $processor->processWebhookEvent($event->id);
        $processor->processWebhookEvent($event->id);

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('analytics_events', 1);
    }

    public function test_successful_renewal_records_an_idempotent_renewal_event(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $plan = $this->plan();
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(12),
        ]);
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedSettlement($payment);

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);

        $this->assertDatabaseHas('analytics_events', [
            'name' => 'renewal_success',
            'event_key' => 'renewal_success:payment:'.$payment->id,
        ]);
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'renewal_success')->count());
    }

    public function test_renewal_starts_after_the_current_term_and_is_scheduled(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $currentEnd = now()->addDays(12)->startOfSecond();
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(10),
            'end_date' => $currentEnd,
        ]);
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order, ['paid_at' => now()]);

        $scheduled = app(SubscriptionLifecycleService::class)->activateFromPayment($order, $payment);

        $this->assertSame(SubscriptionStatus::Pending, $scheduled->status);
        $this->assertTrue($scheduled->start_date->equalTo($currentEnd));
        $this->assertTrue($scheduled->end_date->equalTo($currentEnd->copy()->addDays($plan->duration_days)));
    }

    public function test_delayed_payment_keeps_renewal_continuity_if_the_previous_term_just_expired(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $previousEnd = now()->subDay()->startOfSecond();
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Expired,
            'start_date' => $previousEnd->copy()->subDays($plan->duration_days),
            'end_date' => $previousEnd,
        ]);
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order, ['paid_at' => now()->subDays(2)]);

        $renewal = app(SubscriptionLifecycleService::class)->activateFromPayment($order, $payment);

        $this->assertSame(SubscriptionStatus::Active, $renewal->status);
        $this->assertTrue($renewal->start_date->equalTo($previousEnd));
        $this->assertTrue($renewal->end_date->equalTo($previousEnd->copy()->addDays($plan->duration_days)));
    }

    public function test_transition_command_activates_due_terms_and_expires_ended_terms(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $due = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pending,
            'start_date' => now()->subMinute(),
            'end_date' => now()->addDays(5),
        ]);
        $ended = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(40),
            'end_date' => now()->subSecond(),
        ]);

        $this->artisan('subscriptions:transition')
            ->expectsOutput('Transitioned 2 subscription(s).')
            ->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $due->fresh()->status);
        $this->assertSame(SubscriptionStatus::Expired, $ended->fresh()->status);
    }

    public function test_subscription_status_and_history_are_owner_scoped_and_include_entitlements(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = $this->plan();
        $current = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(28),
        ]);
        Subscription::query()->create([
            'user_id' => $otherUser->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(29),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.current.id', $current->id)
            ->assertJsonPath('data.current.plan.entitlements.free_ads', true)
            ->assertJsonPath('data.current.plan.entitlements.epaper', true)
            ->assertJsonPath('data.current.plan.entitlements.clipping_quota', $plan->clipping_quota)
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('meta.total', 1);
    }

    private function plan(): Plan
    {
        return Plan::query()
            ->where('type', 'free_ads_epaper')
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    private function createOrder(User $user): Order
    {
        $plan = $this->plan();

        return Order::query()->create([
            'order_number' => 'SUB-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function createPayment(Order $order, array $attributes = []): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P01',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
            ...$attributes,
        ]);
    }

    private function signedSettlement(Payment $payment): array
    {
        $grossAmount = number_format($payment->amount, 2, '.', '');
        $statusCode = '200';

        return [
            'order_id' => $payment->merchant_order_id,
            'transaction_id' => 'subscription-transaction-'.$payment->id,
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
