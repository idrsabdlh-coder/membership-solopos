<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_entitlement_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/entitlements')->assertUnauthorized();
    }

    public function test_active_free_ads_plan_returns_a_private_entitlement_response(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('free_ads');
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(6),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/entitlements')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.free_ads', true)
            ->assertJsonPath('data.epaper', false)
            ->assertJsonPath('data.clipping_quota', $plan->clipping_quota)
            ->assertJsonPath('data.download_limit_per_edition', 0)
            ->assertJsonPath('data.plan.type', 'free_ads')
            ->assertJsonPath('data.subscription.id', Subscription::query()->firstOrFail()->id)
            ->assertJsonPath('data.subscription.starts_at', now()->subDay()->timezone('Asia/Jakarta')->toIso8601String());
    }

    public function test_endpoint_returns_no_entitlements_without_an_active_subscription(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('free_ads_epaper');

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Expired,
            'start_date' => now()->subDays(40),
            'end_date' => now()->subDays(10),
        ]);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pending,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(31),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/entitlements')
            ->assertOk()
            ->assertJsonPath('data.free_ads', false)
            ->assertJsonPath('data.epaper', false)
            ->assertJsonPath('data.clipping_quota', 0)
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.plan', null);
    }

    public function test_entitlement_endpoint_excludes_subscriptions_ending_at_the_current_time(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('free_ads_epaper');

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDays(30),
            'end_date' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/entitlements')
            ->assertOk()
            ->assertJsonPath('data.free_ads', false)
            ->assertJsonPath('data.subscription', null);
    }

    public function test_entitlement_endpoint_does_not_return_another_users_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $plan = $this->plan('free_ads');
        Subscription::query()->create([
            'user_id' => $otherUser->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(6),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/entitlements')
            ->assertOk()
            ->assertJsonPath('data.free_ads', false)
            ->assertJsonPath('data.subscription', null);
    }

    private function plan(string $type): Plan
    {
        return Plan::query()
            ->where('type', $type)
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }
}
