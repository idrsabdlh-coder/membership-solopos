<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Clipping;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClippingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_clipping_api_requires_authentication(): void
    {
        $this->getJson('/api/clippings')->assertUnauthorized();
        $this->postJson('/api/clippings', $this->clippingPayload())->assertUnauthorized();
    }

    public function test_active_eligible_subscription_can_create_a_clipping_and_returns_quota(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', $this->clippingPayload())
            ->assertCreated()
            ->assertJsonPath('data.article_ref', 'news-123')
            ->assertJsonPath('data.title', 'Berita pilihan')
            ->assertJsonPath('data.subscription_id', $subscription->id)
            ->assertJsonPath('meta.quota.limit', $subscription->plan->clipping_quota)
            ->assertJsonPath('meta.quota.used', 1)
            ->assertJsonPath('meta.quota.remaining', $subscription->plan->clipping_quota - 1);

        $this->assertDatabaseCount('clippings', 1);
    }

    public function test_duplicate_articles_return_a_conflict_without_creating_a_second_record(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user);
        $this->createClipping($user, $subscription);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', $this->clippingPayload())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'duplicate_article');

        $this->assertDatabaseCount('clippings', 1);
    }

    public function test_create_requires_active_subscription_with_clipping_entitlement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', $this->clippingPayload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'clipping_not_entitled');

        $epaperPlan = $this->plan('epaper');
        $this->createSubscription($user, $epaperPlan);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', $this->clippingPayload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'clipping_not_entitled');

        $this->assertDatabaseCount('clippings', 0);
    }

    public function test_quota_limit_is_checked_before_a_clipping_is_created(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('free_ads');
        $plan->newQuery()->whereKey($plan->id)->update(['clipping_quota' => 1]);
        $plan->refresh();
        $subscription = $this->createSubscription($user, $plan);
        $this->createClipping($user, $subscription);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', [
                ...$this->clippingPayload(),
                'article_ref' => 'another-article',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'clipping_quota_exceeded');

        $this->assertDatabaseCount('clippings', 1);
    }

    public function test_list_is_owner_scoped_searchable_and_includes_quota_state(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $subscription = $this->createSubscription($user);
        $otherSubscription = $this->createSubscription($otherUser);
        $matching = $this->createClipping($user, $subscription);
        $this->createClipping($user, $subscription, [
            'article_ref' => 'sports-999',
            'title' => 'Hasil pertandingan',
        ]);
        $this->createClipping($otherUser, $otherSubscription, [
            'article_ref' => 'news-123',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/clippings?search=Berita')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.quota.used', 2)
            ->assertJsonPath('meta.quota.read_only', false);
    }

    public function test_expired_subscription_clippings_are_read_only_for_90_days_then_hidden(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('free_ads');
        $recentlyExpired = $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::Expired,
            'start_date' => now()->subDays(110),
            'end_date' => now()->subDays(20),
        ]);
        $oldExpired = $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::Expired,
            'start_date' => now()->subDays(200),
            'end_date' => now()->subDays(100),
        ]);
        $keptClipping = $this->createClipping($user, $recentlyExpired);
        $this->createClipping($user, $oldExpired, [
            'article_ref' => 'too-old',
            'title' => 'Too old',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/clippings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $keptClipping->id)
            ->assertJsonPath('meta.quota.read_only', true);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clippings/{$keptClipping->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'clipping_read_only');

        $this->assertDatabaseHas('clippings', ['id' => $keptClipping->id]);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_clippings_from_a_previous_term_remain_read_only_after_renewal(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $previous = $this->createSubscription($user, $plan, [
            'status' => SubscriptionStatus::Expired,
            'start_date' => now()->subDays(40),
            'end_date' => now()->subDays(10),
        ]);
        $current = $this->createSubscription($user, $plan);
        $clipping = $this->createClipping($user, $previous);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/clippings')
            ->assertOk()
            ->assertJsonPath('data.0.read_only', true)
            ->assertJsonPath('meta.quota.read_only', false);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clippings/{$clipping->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'clipping_read_only');

        $this->assertSame(0, Clipping::query()->where('subscription_id', $current->id)->count());
        $this->assertDatabaseHas('clippings', ['id' => $clipping->id]);
    }

    public function test_delete_frees_the_active_subscription_quota(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user);
        $clipping = $this->createClipping($user, $subscription);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clippings/{$clipping->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('clippings', ['id' => $clipping->id]);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', $this->clippingPayload())
            ->assertCreated()
            ->assertJsonPath('meta.quota.used', 1);
    }

    public function test_clipping_validation_restricts_fields_and_url_protocols(): void
    {
        $user = User::factory()->create();
        $this->createSubscription($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clippings', [
                ...$this->clippingPayload(),
                'url' => 'javascript:alert(1)',
                'user_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        $this->assertDatabaseCount('clippings', 0);
    }

    private function plan(string $type = 'free_ads'): Plan
    {
        return Plan::query()
            ->where('type', $type)
            ->where('billing_period', 'monthly')
            ->firstOrFail();
    }

    private function createSubscription(User $user, ?Plan $plan = null, array $attributes = []): Subscription
    {
        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => ($plan ?? $this->plan())->id,
            'status' => SubscriptionStatus::Active,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(29),
            ...$attributes,
        ]);
    }

    private function createClipping(User $user, Subscription $subscription, array $attributes = []): Clipping
    {
        return Clipping::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'article_ref' => 'news-123',
            'title' => 'Berita pilihan',
            'url' => 'https://news.example.com/article/123',
            ...$attributes,
        ]);
    }

    private function clippingPayload(): array
    {
        return [
            'article_ref' => 'news-123',
            'title' => 'Berita pilihan',
            'url' => 'https://news.example.com/article/123',
        ];
    }
}
