<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\SendNotificationDelivery;
use App\Mail\MembershipNoticeMail;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentStatusProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
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

    public function test_successful_payment_queues_an_idempotent_email_with_an_invoice_attachment(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->createOrder();
        $payment = $this->createPayment($order);

        app(PaymentStatusProcessor::class)->processStatusResponse(
            $this->paymentPayload($payment, 'settlement')
        );

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('order.payment_succeeded', $delivery->type);
        $this->assertSame('queued', $delivery->status);

        $job = new SendNotificationDelivery($delivery->id);
        $job->handle(app(InvoiceService::class));
        $job->handle(app(InvoiceService::class));

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Mail::assertSentTimes(MembershipNoticeMail::class, 1);
        Mail::assertSent(MembershipNoticeMail::class, fn (MembershipNoticeMail $mail): bool => $mail->hasTo($delivery->recipient)
            && $mail->invoicePdf !== null
            && $mail->attachments() !== []
            && $mail->invoiceFilename === 'invoice-'.$order->order_number.'.pdf');
    }

    public function test_failed_payment_notification_is_logged_and_includes_invoice(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->createOrder();
        $payment = $this->createPayment($order);

        app(PaymentStatusProcessor::class)->processStatusResponse(
            $this->paymentPayload($payment, 'deny')
        );

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('order.payment_failed', $delivery->type);
        $this->assertSame('pending', $order->fresh()->status->value);

        (new SendNotificationDelivery($delivery->id))->handle(app(InvoiceService::class));

        $this->assertSame('sent', $delivery->fresh()->status);
        Mail::assertSent(MembershipNoticeMail::class, fn (MembershipNoticeMail $mail): bool => $mail->invoicePdf !== null
            && $mail->attachments() !== []);

        $expiredPayment = $this->createPayment($order);
        app(PaymentStatusProcessor::class)->processStatusResponse(
            $this->paymentPayload($expiredPayment, 'expire')
        );
        $this->assertDatabaseHas('notification_deliveries', [
            'type' => 'order.payment_expired',
            'source_id' => $expiredPayment->id,
        ]);
    }

    public function test_daily_subscription_command_sends_h7_reminders_once_and_skips_scheduled_renewals(): void
    {
        Mail::fake();
        Queue::fake();
        $user = User::factory()->create();
        $plan = $this->plan();
        $h7EndDate = $this->endDateAfterLocalDays(7);
        $h3EndDate = $this->endDateAfterLocalDays(3);
        $h1EndDate = $this->endDateAfterLocalDays(1);
        $subscription = $this->createSubscription($user, $plan, $h7EndDate);
        $this->createSubscription(User::factory()->create(), $plan, $h3EndDate);
        $this->createSubscription(User::factory()->create(), $plan, $h1EndDate);

        $this->artisan('subscriptions:send-notifications')->assertSuccessful();
        $this->artisan('subscriptions:send-notifications')->assertSuccessful();

        $this->assertDatabaseCount('notification_deliveries', 3);
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'subscription.reminder.h7']);
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'subscription.reminder.h3']);
        $this->assertDatabaseHas('notification_deliveries', ['type' => 'subscription.reminder.h1']);

        $h7Delivery = NotificationDelivery::query()
            ->where('type', 'subscription.reminder.h7')
            ->firstOrFail();
        (new SendNotificationDelivery($h7Delivery->id))->handle(app(InvoiceService::class));
        $this->assertSame('sent', $h7Delivery->fresh()->status);
        Mail::assertSent(MembershipNoticeMail::class, fn (MembershipNoticeMail $mail): bool => $mail->invoicePdf === null);

        $renewedUser = User::factory()->create();
        $renewalEnd = $h7EndDate->copy()->addDays(30);
        $current = $this->createSubscription(
            $renewedUser,
            $plan,
            $h7EndDate,
            ['start_date' => now()->subDays(23)]
        );
        $this->createSubscription($renewedUser, $plan, $renewalEnd, [
            'status' => SubscriptionStatus::Pending,
            'start_date' => $h7EndDate,
        ]);

        $this->artisan('subscriptions:send-notifications')->assertSuccessful();

        $this->assertSame(0, NotificationDelivery::query()
            ->where('source_id', $current->id)
            ->count());
        $this->assertSame(3, NotificationDelivery::query()->count());
        $this->assertModelExists($subscription);
    }

    public function test_expired_subscription_is_notified_only_when_no_later_term_exists(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $plan = $this->plan();
        $ended = $this->createSubscription($user, $plan, now()->subMinute());

        $this->artisan('subscriptions:transition')->assertSuccessful();

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('subscription.expired', $delivery->type);
        $this->assertSame(SubscriptionStatus::Expired, $ended->fresh()->status);

        $renewedUser = User::factory()->create();
        $renewedEnd = now()->addDays(30);
        $this->createSubscription($renewedUser, $plan, now()->subMinute());
        $this->createSubscription($renewedUser, $plan, $renewedEnd, [
            'status' => SubscriptionStatus::Pending,
            'start_date' => now()->subMinute(),
        ]);

        $this->artisan('subscriptions:transition')->assertSuccessful();
        $this->assertSame(1, NotificationDelivery::query()->count());
    }

    public function test_order_expiry_creates_a_delivery_record_with_invoice_context(): void
    {
        Queue::fake();
        $order = $this->createOrder([
            'expires_at' => now()->subSecond(),
        ]);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('order.expired', $delivery->type);
        $this->assertSame($order->order_number, $delivery->context['order_number']);
        $this->assertSame(OrderStatus::Expired->value, $delivery->context['order_status']);

        (new SendNotificationDelivery($delivery->id))->failed(new RuntimeException('Mail transport unavailable.'));
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame('RuntimeException', $delivery->fresh()->failure_reason);

        $this->artisan('notifications:retry-failed')
            ->expectsOutput('Queued 1 failed notification(s) for retry.')
            ->assertSuccessful();
        $this->assertSame('queued', $delivery->fresh()->status);
    }

    private function endDateAfterLocalDays(int $days): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Jakarta')
            ->addDays($days)
            ->setTime(16, 0)
            ->setTimezone(config('app.timezone'));
    }

    private function plan(): Plan
    {
        return Plan::query()
            ->where('type', 'free_ads_epaper')
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrder(array $attributes = []): Order
    {
        $user = User::factory()->create();
        $plan = $this->plan();

        return Order::query()->create([
            'order_number' => 'NOTIFY-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
            ...$attributes,
        ]);
    }

    private function createPayment(Order $order): Payment
    {
        $attempt = $order->payments()->count() + 1;

        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'merchant_order_id' => $order->order_number.'-P'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT),
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);
    }

    private function paymentPayload(Payment $payment, string $status): array
    {
        return [
            'order_id' => $payment->merchant_order_id,
            'transaction_id' => 'notification-test-'.$payment->id,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'gross_amount' => number_format($payment->amount, 2, '.', ''),
            'payment_type' => $payment->method,
            'settlement_time' => now()->toIso8601String(),
            'currency' => 'IDR',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubscription(
        User $user,
        Plan $plan,
        \DateTimeInterface $endDate,
        array $attributes = [],
    ): Subscription {
        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(29),
            'end_date' => $endDate,
            ...$attributes,
        ]);
    }
}
