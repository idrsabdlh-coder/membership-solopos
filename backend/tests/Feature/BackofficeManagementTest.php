<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BackofficeAuditService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BackofficeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'backoffice.admin_emails' => [],
            'backoffice.staff_emails' => [],
        ]);
        $this->seed(PlanSeeder::class);
    }

    public function test_backoffice_returns_401_without_authentication_and_403_for_members(): void
    {
        $this->getJson('/api/admin/customers')->assertUnauthorized();

        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'backoffice_access_required');
    }

    public function test_configured_staff_are_not_returned_as_customers_case_insensitively(): void
    {
        config(['backoffice.staff_emails' => ['configured-staff@example.test']]);
        $staff = User::factory()->create(['email' => 'Configured-Staff@Example.Test']);

        $staffActor = $this->staff();
        $this->actingAs($staffActor, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertOk()
            ->assertJsonMissing(['email' => $staff->email]);

        $csv = $this->actingAs($staffActor, 'sanctum')
            ->get('/api/admin/reports/customers.csv')
            ->assertOk()
            ->streamedContent();
        $this->assertStringNotContainsString('configured-staff@example.test', strtolower($csv));
    }

    public function test_staff_can_read_customers_transactions_and_exports_but_cannot_manage_catalog_or_roles(): void
    {
        $staff = $this->staff();
        $customer = User::factory()->create([
            'name' => 'Member Customer',
            'email' => 'customer@example.test',
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'customer@example.test')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.avatar_path');

        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/orders')->assertOk();
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/payments')->assertOk();
        $this->actingAs($staff, 'sanctum')->get('/api/admin/reports/customers.csv')->assertOk();
        $this->actingAs($staff, 'sanctum')->get('/api/admin/reports/transactions.csv')->assertOk();

        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/plans')->assertForbidden();
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/audit-logs')->assertForbidden();
        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/admin/users/{$customer->id}/role", ['role' => 'staff'])
            ->assertForbidden();
        $this->assertSame(UserRole::Member, $customer->fresh()->role);
    }

    public function test_unverified_allowlisted_admin_cannot_access_backoffice(): void
    {
        config(['backoffice.admin_emails' => ['admin@example.test']]);
        $admin = User::factory()->unverified()->create(['email' => 'admin@example.test']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_audit_plan_catalog_changes(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('free_ads', 'yearly');
        $plan->delete();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/plans', [
                'type' => 'free_ads',
                'name' => 'Free Ads Annual',
                'billing_period' => 'yearly',
                'price' => 150000,
                'clipping_quota' => 200,
                'download_limit_per_edition' => 0,
                'download_limit_per_day' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.duration_days', 365)
            ->assertJsonPath('data.price', 150000)
            ->assertJsonPath('data.is_active', true);
        $createdPlanId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/plans/{$createdPlanId}", [
                'price' => 175000,
                'clipping_quota' => 220,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', 175000)
            ->assertJsonPath('data.clipping_quota', 220)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('plans', [
            'id' => $createdPlanId,
            'price' => 175000,
            'duration_days' => 365,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'plan.created',
            'subject_id' => $createdPlanId,
        ]);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'plan.updated',
            'subject_id' => $createdPlanId,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?action=plan.updated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.metadata.before.price', 150000)
            ->assertJsonPath('data.0.metadata.after.price', 175000);
    }

    public function test_plan_updates_cannot_change_package_identity_or_duration(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('free_ads', 'monthly');
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'type' => 'epaper',
                'billing_period' => 'yearly',
                'duration_days' => 365,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'billing_period', 'duration_days']);

        $this->assertSame('free_ads', $plan->fresh()->type->value);
        $this->assertSame('monthly', $plan->fresh()->billing_period->value);
        $this->assertSame(30, $plan->fresh()->duration_days);
        $this->assertDatabaseCount('backoffice_audit_logs', 0);
    }

    public function test_invalid_plan_type_shapes_return_validation_errors(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/plans', [
                'type' => ['unexpected'],
                'name' => 'Bad plan',
                'billing_period' => 'monthly',
                'price' => 10000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_admin_can_create_vouchers_without_client_controlled_usage_and_preserve_order_history(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                'code' => ' launch25 ',
                'type' => 'percent',
                'value' => 25,
                'quota' => 3,
                'used' => 100,
                'valid_from' => '2026-10-01 00:00:00',
                'valid_until' => '2026-11-01 00:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('used');
        $this->assertDatabaseCount('vouchers', 0);

        $voucherResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                'code' => ' launch25 ',
                'type' => 'percent',
                'value' => 25,
                'quota' => 3,
                'valid_from' => '2026-10-01 00:00:00',
                'valid_until' => '2026-11-01 00:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LAUNCH25')
            ->assertJsonPath('data.used', 0);
        $voucher = Voucher::query()->findOrFail($voucherResponse->json('data.id'));

        $voucher->increment('used', 2);
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/vouchers/{$voucher->id}", ['quota' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quota.0', 'Kuota tidak boleh lebih kecil dari jumlah yang sudah terpakai atau dipesan.');

        $order = $this->createOrder(User::factory()->create(), $this->plan('free_ads', 'monthly'), $voucher);
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/vouchers/{$voucher->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', 'voucher_has_orders');

        $this->assertModelExists($voucher->fresh());
        $this->assertSame($voucher->id, $order->fresh()->voucher_id);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'action' => 'voucher.created',
            'subject_id' => $voucher->id,
        ]);
    }

    public function test_percent_vouchers_are_limited_to_one_hundred_and_partial_dates_are_validated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                'code' => 'OVER100',
                'type' => 'percent',
                'value' => 101,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                'code' => 'PERCENT100',
                'type' => 'percent',
                'value' => 100,
                'valid_from' => '2026-10-01 00:00:00',
                'valid_until' => '2026-11-01 00:00:00',
            ])
            ->assertCreated();
        $voucherId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/vouchers/{$voucherId}", [
                'valid_from' => '2026-12-01 00:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valid_until');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/vouchers/{$voucherId}", [
                'value' => 101,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/vouchers/{$voucherId}", [
                'type' => 'fixed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/vouchers/{$voucherId}", [
                'type' => 'fixed',
                'value' => 250000,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.value', 250000);
    }

    public function test_plan_changes_roll_back_when_audit_recording_fails(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('free_ads', 'monthly');
        $originalPrice = $plan->price;

        $audit = $this->mock(BackofficeAuditService::class);
        $audit->shouldReceive('record')
            ->once()
            ->andThrow(new RuntimeException('Audit storage unavailable.'));

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/plans/{$plan->id}", ['price' => 31000])
            ->assertServerError();

        $this->assertSame($originalPrice, $plan->fresh()->price);
        $this->assertDatabaseCount('backoffice_audit_logs', 0);
    }

    public function test_admin_can_assign_staff_but_cannot_assign_admin_through_the_api(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/role", ['role' => 'staff'])
            ->assertOk()
            ->assertJsonPath('data.role', 'staff');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/role", ['role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(UserRole::Staff, $user->fresh()->role);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'action' => 'user.role_updated',
            'actor_id' => $admin->id,
            'subject_id' => $user->id,
        ]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertOk();
    }

    public function test_admin_views_orders_and_payments_without_receiving_provider_secrets(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $order = $this->createOrder($customer, $this->plan('free_ads', 'monthly'));
        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'gateway_ref' => 'private-provider-reference',
            'merchant_order_id' => 'private-merchant-order-id',
            'snap_token' => 'private-snap-token',
            'redirect_url' => 'https://example.test/redirect',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Success,
            'raw_payload' => ['signature_key' => 'private-signature'],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $order->order_number)
            ->assertJsonPath('data.0.customer.email', $customer->email)
            ->assertJsonPath('data.0.payment.status', 'success')
            ->assertJsonMissingPath('data.0.payment.snap_token')
            ->assertJsonMissingPath('data.0.payment.redirect_url')
            ->assertJsonMissingPath('data.0.payment.raw_payload');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/payments?status=success')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order.id', $order->id)
            ->assertJsonPath('data.0.order.customer.email', $customer->email)
            ->assertJsonMissingPath('data.0.raw_payload')
            ->assertJsonMissingPath('data.0.merchant_order_id');

        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'order.listed']);
        $this->assertDatabaseHas('backoffice_audit_logs', ['action' => 'payment.listed']);
    }

    public function test_customer_and_transaction_csv_exports_neutralize_formula_cells_and_are_audited(): void
    {
        $staff = $this->staff();
        $customer = User::factory()->create([
            'name' => '=HYPERLINK("https://example.test")',
            'email' => 'csv-customer@example.test',
        ]);
        $order = $this->createOrder($customer, $this->plan('free_ads', 'monthly'));
        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Success,
        ]);

        $customersCsv = $this->actingAs($staff, 'sanctum')
            ->get('/api/admin/reports/customers.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();
        $transactionsCsv = $this->actingAs($staff, 'sanctum')
            ->get('/api/admin/reports/transactions.csv?status=paid')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $customersCsv);
        $this->assertStringNotContainsString('=HYPERLINK', str_replace("'=HYPERLINK", '', $customersCsv));
        $this->assertStringContainsString($order->order_number, $transactionsCsv);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'action' => 'report.customers.exported',
            'actor_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('backoffice_audit_logs', [
            'action' => 'report.transactions.exported',
            'actor_id' => $staff->id,
        ]);
    }

    public function test_backoffice_admin_role_can_manage_e_paper_without_the_legacy_allowlist(): void
    {
        $admin = $this->admin();
        config(['epaper.admin_emails' => []]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/epaper/editions')
            ->assertOk();
    }

    private function admin(): User
    {
        config(['backoffice.admin_emails' => ['admin@example.test']]);

        return User::factory()->create(['email' => 'admin@example.test']);
    }

    private function staff(): User
    {
        $staff = User::factory()->create();
        User::query()->whereKey($staff->id)->update(['role' => UserRole::Staff->value]);

        return $staff->refresh();
    }

    private function plan(string $type, string $period): Plan
    {
        return Plan::query()
            ->where('type', $type)
            ->where('billing_period', $period)
            ->firstOrFail();
    }

    private function createOrder(User $user, Plan $plan, ?Voucher $voucher = null): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'voucher_id' => $voucher?->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Paid,
            'expires_at' => now()->addDay(),
        ]);
    }
}
