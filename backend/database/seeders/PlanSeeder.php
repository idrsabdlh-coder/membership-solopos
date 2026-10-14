<?php
// database/seeders/PlanSeeder.php
namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            'weekly'  => ['period' => BillingPeriod::Weekly,  'label' => 'Mingguan', 'days' => 7,   'clip' => 5],
            'monthly' => ['period' => BillingPeriod::Monthly, 'label' => 'Bulanan',  'days' => 30,  'clip' => 20],
            'yearly'  => ['period' => BillingPeriod::Yearly,  'label' => 'Tahunan',  'days' => 365, 'clip' => 200],
        ];

        // Harga free_ads_epaper dari PRD. Harga free_ads dan epaper adalah
        // PLACEHOLDER karena PRD menyerahkannya ke tim bisnis.
        $types = [
            ['type' => PlanType::FreeAdsEpaper, 'name' => 'Free Ads & E-Paper', 'clip' => true,  'dl' => true,  'price' => [10000, 30000, 300000]],
            ['type' => PlanType::FreeAds,       'name' => 'Free Ads',           'clip' => true,  'dl' => false, 'price' => [5000, 15000, 150000]],
            ['type' => PlanType::Epaper,        'name' => 'E-Paper',            'clip' => false, 'dl' => true,  'price' => [8000, 25000, 250000]],
        ];

        foreach ($types as $t) {
            $i = 0;
            foreach ($periods as $key => $p) {
                Plan::updateOrCreate(
                    ['type' => $t['type'], 'billing_period' => $p['period']],
                    [
                        'name' => "{$t['name']} {$p['label']}",
                        'duration_days' => $p['days'],
                        'price' => $t['price'][$i],
                        'clipping_quota' => $t['clip'] ? $p['clip'] : 0,
                        'download_limit_per_edition' => $t['dl'] ? 3 : 0,
                        'download_limit_per_day' => $t['dl'] ? 10 : 0,
                        'is_recommended' => $key === 'monthly',
                        'is_active' => true,
                    ]
                );
                $i++;
            }
        }
    }
}