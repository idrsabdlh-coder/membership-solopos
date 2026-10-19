<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'billing_period' => BillingPeriod::class,
            'duration_days' => 'integer',
            'price' => 'integer',
            'clipping_quota' => 'integer',
            'download_limit_per_edition' => 'integer',
            'download_limit_per_day' => 'integer',
            'is_recommended' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function includesFreeAds(): bool
    {
        return in_array($this->type, [PlanType::FreeAdsEpaper, PlanType::FreeAds], true);
    }

    public function includesEpaper(): bool
    {
        return in_array($this->type, [PlanType::FreeAdsEpaper, PlanType::Epaper], true);
    }
}
