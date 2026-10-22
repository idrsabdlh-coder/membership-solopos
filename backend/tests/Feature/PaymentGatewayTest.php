<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PaymentStatusProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
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

    public function test_it_creates_a_midtrans_snap_transaction_for_the_order_amount(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirect/snap-token-test',
            ], 201),
        ]);

        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'qris'])
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'midtrans')
            ->assertJsonPath('data.method', 'qris')
            ->assertJsonPath('data.amount', $order->total)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.redirect_url', 'https://app.sandbox.midtrans.com/snap/v4/redirect/snap-token-test')
            ->assertJsonMissingPath('data.snap_token');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('midtrans', $payment->gateway);
        $this->assertSame($order->total, $payment->amount);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->merchant_order_id);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.sandbox.midtrans.com/snap/v1/transactions'
            && $request['transaction_details']['gross_amount'] === $order->total
            && $request['enabled_payments'] === ['qris']
            && ! array_key_exists('phone', $request['customer_details'])
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic '));
    }

    public function test_repeated_pay_requests_reuse_the_same_pending_transaction(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/redirect',
            ], 201),
        ]);

        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'bank_transfer'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'bank_transfer'])
            ->assertOk()
            ->assertJsonPath('data.redirect_url', 'https://app.sandbox.midtrans.com/redirect');

        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => $request['enabled_payments']
            === ['bca_va', 'bni_va', 'bri_va', 'echannel', 'permata_va']);
    }

    public function test_payment_cannot_be_started_for_an_order_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createOrder($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'qris'])
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_only_supported_payment_methods_are_accepted(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'credit_card'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_gateway_errors_are_reported_without_leaking_provider_response(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['secret provider detail'],
            ], 500),
        ]);

        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay", ['method' => 'gopay'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Transaksi pembayaran belum dapat dibuat. Silakan coba lagi.')
            ->assertDontSee('secret provider detail');

        $this->assertSame(PaymentStatus::Failed, Payment::query()->firstOrFail()->status);
    }

    public function test_invalid_webhook_signatures_are_rejected_without_persisting_events(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedNotification($payment);
        $payload['signature_key'] = str_repeat('0', 128);

        $this->postJson('/api/payments/webhook', $payload)->assertForbidden();

        $this->assertDatabaseCount('payment_webhook_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_valid_and_duplicate_webhooks_are_persisted_idempotently(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedNotification($payment);

        $this->postJson('/api/payments/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('received', true);
        $this->postJson('/api/payments/webhook', $payload)
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->assertDatabaseCount('payment_webhook_events', 1);
        Queue::assertPushed(ProcessPaymentWebhook::class);
    }

    public function test_successful_webhook_marks_payment_and_order_paid_idempotently(): void
    {
        Event::fake([OrderPaid::class]);
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedNotification($payment);

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();

        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_webhook_amount_mismatch_is_not_applied(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order);
        $payload = $this->signedNotification($payment, grossAmount: '1.00');

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();

        try {
            app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);
            $this->fail('Mismatched webhook amount should fail processing.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Payment notification amount or currency does not match the order.', $exception->getMessage());
        }

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertNull($event->fresh()->processed_at);
    }

    public function test_order_resource_includes_the_latest_payment_attempt(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order, [
            'snap_token' => 'private-snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/redirect',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.id', $payment->id)
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.redirect_url', 'https://app.sandbox.midtrans.com/redirect')
            ->assertJsonMissingPath('data.payment.snap_token');
    }

    public function test_payment_status_endpoint_reconciles_with_midtrans(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => 'ORD-STATUS-P01',
                'transaction_id' => 'midtrans-status-transaction',
                'transaction_status' => 'settlement',
                'status_code' => '200',
                'gross_amount' => '30000.00',
                'payment_type' => 'bank_transfer',
                'settlement_time' => '2026-10-22 10:00:00',
                'currency' => 'IDR',
            ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment($order, [
            'merchant_order_id' => 'ORD-STATUS-P01',
            'amount' => 30000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->id}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'paid')
            ->assertJsonPath('data.payment.status', 'success');

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_paid_order_restores_voucher_usage_if_it_had_expired_before_late_webhook(): void
    {
        $user = User::factory()->create();
        $voucher = Voucher::query()->create([
            'code' => 'LATE',
            'type' => 'fixed',
            'value' => 1000,
            'quota' => 10,
            'used' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);
        $order = $this->createOrder($user, [
            'voucher_id' => $voucher->id,
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
        $voucher->update(['used' => 0]);
        $payment = $this->createPayment($order);
        $payload = $this->signedNotification($payment);

        $this->postJson('/api/payments/webhook', $payload)->assertOk();
        $event = PaymentWebhookEvent::query()->firstOrFail();
        app(PaymentStatusProcessor::class)->processWebhookEvent($event->id);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, $voucher->fresh()->used);
    }

    private function createOrder(User $user, array $attributes = []): Order
    {
        $plan = Plan::query()
            ->where('type', 'free_ads_epaper')
            ->where('billing_period', 'monthly')
            ->firstOrFail();

        return Order::query()->create([
            'order_number' => 'ORD-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addHours(24),
            ...$attributes,
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

    private function signedNotification(Payment $payment, ?string $grossAmount = null): array
    {
        $grossAmount ??= number_format($payment->amount, 2, '.', '');
        $statusCode = '200';

        return [
            'order_id' => $payment->merchant_order_id,
            'transaction_id' => 'transaction-'.$payment->id,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'payment_type' => 'qris',
            'settlement_time' => '2026-10-22 10:00:00',
            'currency' => 'IDR',
            'signature_key' => hash(
                'sha512',
                $payment->merchant_order_id.$statusCode.$grossAmount.config('services.midtrans.server_key')
            ),
        ];
    }
}
