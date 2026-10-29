<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PrivacyDataRequest;
use App\Models\User;
use App\Models\UserConsentRecord;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'privacy.consent_versions' => [
                'privacy_notice' => '2026-01',
                'terms' => '2026-01',
                'epaper_watermark' => '2026-02',
            ],
        ]);
        $this->seed(PlanSeeder::class);
    }

    public function test_consent_records_are_versioned_immutable_and_user_scoped(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/consents', [
                'type' => 'privacy_notice',
                'version' => '2026-01',
                'accepted' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.accepted', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/consents', [
                'type' => 'privacy_notice',
                'version' => '2026-01',
                'accepted' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.accepted', false);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/consents')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.0.accepted', false)
            ->assertJsonCount(2, 'history');

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/auth/consents')
            ->assertOk()
            ->assertJsonCount(0, 'history');

        $this->assertSame(2, UserConsentRecord::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, UserConsentRecord::query()->where('user_id', $otherUser->id)->count());
    }

    public function test_consent_requires_current_configured_document_version(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/consents', [
                'type' => 'privacy_notice',
                'version' => 'old-version',
                'accepted' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        config(['privacy.consent_versions.privacy_notice' => null]);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/consents', [
                'type' => 'privacy_notice',
                'version' => '2026-01',
                'accepted' => true,
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'privacy_policy_not_configured');

        $this->assertDatabaseCount('user_consent_records', 0);
    }

    public function test_personal_data_export_is_owner_scoped_and_excludes_payment_secrets(): void
    {
        $user = User::factory()->create([
            'name' => 'Exported Member',
            'whatsapp' => '+6281234567890',
        ]);
        $otherUser = User::factory()->create();
        $plan = $this->plan();
        $order = $this->createOrder($user, $plan);
        $otherOrder = $this->createOrder($otherUser, $plan);

        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'gateway_ref' => 'private-provider-reference',
            'merchant_order_id' => 'private-merchant-order-id',
            'snap_token' => 'private-snap-token',
            'redirect_url' => 'https://example.test/secret',
            'method' => 'qris',
            'amount' => $order->total,
            'status' => PaymentStatus::Success,
            'raw_payload' => ['signature_key' => 'private-signature'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/privacy/export')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Disposition', 'attachment; filename="membership-personal-data.json"')
            ->assertJsonPath('account.email', $user->email)
            ->assertJsonPath('account.whatsapp', '+6281234567890')
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_number', $order->order_number)
            ->assertJsonMissing(['order_number' => $otherOrder->order_number])
            ->assertJsonMissing(['signature_key' => 'private-signature']);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('private-snap-token', $content);
        $this->assertStringNotContainsString('private-provider-reference', $content);
        $this->assertStringNotContainsString('private-merchant-order-id', $content);
        $this->assertStringNotContainsString('private-signature', $content);
        $this->assertStringNotContainsString('password', $content);

        config(['session.driver' => 'array']);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/privacy/export')
            ->assertServiceUnavailable();
    }

    public function test_erasure_request_requires_password_is_idempotent_and_does_not_delete_account(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, $this->plan());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/privacy/deletion-requests', [
                'current_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $payload = [
            'current_password' => 'password',
            'reason' => 'Saya tidak ingin menggunakan layanan lagi.',
        ];
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/privacy/deletion-requests', $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');
        $requestId = $response->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/privacy/deletion-requests', $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.id', $requestId);

        $this->assertSame(1, PrivacyDataRequest::query()->where('user_id', $user->id)->count());
        $this->assertModelExists($user->fresh());
        $this->assertModelExists($order->fresh());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/auth/privacy/deletion-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_reviews_erasure_requests_with_audited_state_transitions(): void
    {
        config(['backoffice.admin_emails' => ['privacy-admin@example.test']]);
        $user = User::factory()->create();
        $admin = User::factory()->create(['email' => 'privacy-admin@example.test']);

        $requestId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/privacy/deletion-requests', [
                'current_password' => 'password',
            ])
            ->assertStatus(202)
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/privacy/deletion-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', $user->email);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/privacy/deletion-requests/{$requestId}", ['status' => 'in_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.reviewer.id', $admin->id);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/privacy/deletion-requests/{$requestId}", [
                'status' => 'fulfilled',
                'resolution_note' => 'Permintaan ditangani setelah pemeriksaan manual kebijakan retensi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/privacy/deletion-requests/{$requestId}", [
                'status' => 'rejected',
                'resolution_note' => 'Tidak diizinkan.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('backoffice_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'privacy.erasure_request.status_changed',
            'subject_id' => $requestId,
        ]);
        $this->assertModelExists($user->fresh());
        $this->assertSame('fulfilled', PrivacyDataRequest::query()->findOrFail($requestId)->status);
    }

    public function test_privacy_endpoints_require_authentication(): void
    {
        $this->getJson('/api/auth/consents')->assertUnauthorized();
        $this->postJson('/api/auth/consents', [])->assertUnauthorized();
        $this->getJson('/api/auth/privacy/export')->assertUnauthorized();
        $this->getJson('/api/auth/privacy/deletion-requests')->assertUnauthorized();
        $this->postJson('/api/auth/privacy/deletion-requests', [])->assertUnauthorized();
        $this->getJson('/api/admin/privacy/deletion-requests')->assertUnauthorized();
    }

    private function plan(): Plan
    {
        return Plan::query()
            ->where('type', 'free_ads')
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    private function createOrder(User $user, Plan $plan): Order
    {
        return Order::query()->create([
            'order_number' => 'PRIVACY-'.strtoupper((string) str()->ulid()),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subtotal' => $plan->price,
            'discount' => 0,
            'total' => $plan->price,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
    }
}
