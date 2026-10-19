<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_public_catalog_contract_and_cache_header(): void
    {
        $this->seed(PlanSeeder::class);

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertJsonCount(9, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'type_label',
                        'name',
                        'billing_period',
                        'period_label',
                        'duration_days',
                        'price',
                        'clipping_quota',
                        'download_limit_per_edition',
                        'download_limit_per_day',
                        'includes_free_ads',
                        'includes_epaper',
                        'is_recommended',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.billing_period', 'weekly')
            ->assertJsonPath('data.0.duration_days', 7)
            ->assertJsonPath('data.0.type', 'epaper')
            ->assertJsonPath('data.0.includes_free_ads', false)
            ->assertJsonPath('data.0.includes_epaper', true)
            ->assertJsonPath('data.2.type', 'free_ads_epaper')
            ->assertJsonPath('data.2.includes_free_ads', true)
            ->assertJsonPath('data.2.includes_epaper', true);
    }

    public function test_it_filters_by_plan_type_and_billing_period(): void
    {
        $this->seed(PlanSeeder::class);

        $this->getJson('/api/plans?type=epaper&period=monthly')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'epaper')
            ->assertJsonPath('data.0.billing_period', 'monthly')
            ->assertJsonPath('data.0.duration_days', 30);
    }

    public function test_it_excludes_inactive_plans(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->where('type', 'epaper')->where('billing_period', 'weekly')->update(['is_active' => false]);

        $this->getJson('/api/plans?type=epaper')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['billing_period' => 'weekly']);
    }

    public function test_it_returns_an_empty_collection_when_no_plans_match(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->update(['is_active' => false]);

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $this->getJson('/api/plans?type=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->getJson('/api/plans?period=quarterly')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }
}
