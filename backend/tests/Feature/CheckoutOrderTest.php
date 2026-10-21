<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_order_creation_calculates_amounts_from_the_active_plan_and_reserves_voucher_quota(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $voucher = $this->createVoucher([
            'code' => 'SAVE25',
            'type' => 'percent',
            'value' => 25,
            'quota' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'plan_id' => $plan->id,
                'voucher_code' => ' save25 ',
                'subtotal' => 1,
                'discount' => 0,
                'total' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.voucher.code', 'SAVE25')
            ->assertJsonPath('data.subtotal', 30000)
            ->assertJsonPath('data.discount', 7500)
            ->assertJsonPath('data.total', 22500)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.expires_at', now()->addHours(24)->timezone('Asia/Jakarta')->toIso8601String());

        $this->assertSame(1, $voucher->fresh()->used);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_a_voucher_quota_cannot_be_reserved_by_more_than_one_pending_order(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $this->createVoucher(['code' => 'ONCE', 'quota' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', ['plan_id' => $plan->id, 'voucher_code' => 'ONCE'])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', ['plan_id' => $plan->id, 'voucher_code' => 'ONCE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('voucher_code');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_fixed_voucher_discount_is_capped_at_the_order_subtotal(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $this->createVoucher([
            'code' => 'FIXED',
            'type' => 'fixed',
            'value' => 50000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'plan_id' => $plan->id,
                'voucher_code' => 'FIXED',
            ])
            ->assertCreated()
            ->assertJsonPath('data.discount', 30000)
            ->assertJsonPath('data.total', 0);
    }

    public function test_voucher_validation_returns_a_quote_without_reserving_quota(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $voucher = $this->createVoucher([
            'code' => 'QUOTE',
            'type' => 'fixed',
            'value' => 5000,
            'quota' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vouchers/validate', [
                'plan_id' => $plan->id,
                'voucher_code' => 'QUOTE',
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 30000)
            ->assertJsonPath('data.discount', 5000)
            ->assertJsonPath('data.total', 25000)
            ->assertJsonPath('data.voucher.code', 'QUOTE');

        $this->assertSame(0, $voucher->fresh()->used);
    }

    public function test_invalid_expired_not_yet_valid_and_exhausted_vouchers_are_rejected(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $this->createVoucher(['code' => 'EXPIRED', 'valid_until' => now()->subSecond()]);
        $this->createVoucher(['code' => 'FUTURE', 'valid_from' => now()->addDay()]);
        $this->createVoucher(['code' => 'USED', 'quota' => 1, 'used' => 1]);

        foreach (['MISSING', 'EXPIRED', 'FUTURE', 'USED'] as $code) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/orders', [
                    'plan_id' => $plan->id,
                    'voucher_code' => $code,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('voucher_code');
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_voucher_definitions_are_not_applied(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $this->createVoucher(['code' => 'OVER100', 'type' => 'percent', 'value' => 101]);
        $this->createVoucher(['code' => 'UNKNOWN', 'type' => 'other', 'value' => 10]);
        $this->createVoucher(['code' => 'ZERO', 'type' => 'fixed', 'value' => 0]);

        foreach (['OVER100', 'UNKNOWN', 'ZERO'] as $code) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/vouchers/validate', [
                    'plan_id' => $plan->id,
                    'voucher_code' => $code,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('voucher_code');
        }
    }

    public function test_inactive_plans_cannot_be_quoted_or_ordered(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        Plan::query()->whereKey($plan->id)->update(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/vouchers/validate', [
                'plan_id' => $plan->id,
                'voucher_code' => 'ANY',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_multiple_vouchers_and_upgrade_credit_are_not_accepted(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'plan_id' => $plan->id,
                'voucher_codes' => ['FIRST', 'SECOND'],
                'upgrade_credit' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voucher_codes', 'upgrade_credit']);
    }

    public function test_order_status_is_visible_only_to_its_owner_and_marks_overdue_orders_expired(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = $this->monthlyPlan();
        $order = Order::query()->create([
            'order_number' => 'ORD-TEST-OWNER',
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/orders/'.$order->id)
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
    }

    public function test_expiration_command_releases_reserved_voucher_quota(): void
    {
        $user = User::factory()->create();
        $plan = $this->monthlyPlan();
        $voucher = $this->createVoucher([
            'code' => 'RESERVED',
            'quota' => 1,
            'used' => 1,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-TEST-EXPIRE',
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'voucher_id' => $voucher->id,
            'subtotal' => $plan->price,
            'discount' => 1000,
            'total' => $plan->price - 1000,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->subSecond(),
        ]);

        $this->artisan('orders:expire-pending')
            ->expectsOutput('Expired 1 pending order(s).')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(0, $voucher->fresh()->used);
    }

    public function test_order_creation_and_voucher_validation_require_authentication(): void
    {
        $plan = $this->monthlyPlan();

        $this->postJson('/api/orders', ['plan_id' => $plan->id])->assertUnauthorized();
        $this->postJson('/api/vouchers/validate', [
            'plan_id' => $plan->id,
            'voucher_code' => 'CODE',
        ])->assertUnauthorized();
        $this->getJson('/api/orders/1')->assertUnauthorized();
    }

    private function monthlyPlan(): Plan
    {
        return Plan::query()
            ->where('type', 'free_ads_epaper')
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    private function createVoucher(array $attributes = []): Voucher
    {
        return Voucher::query()->create([
            'code' => 'TEST',
            'type' => 'fixed',
            'value' => 1000,
            'quota' => null,
            'used' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            ...$attributes,
        ]);
    }
}
