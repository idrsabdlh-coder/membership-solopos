<?php
// app/Http/Resources/PlanResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'billing_period' => $this->billing_period->value,
            'period_label' => $this->billing_period->label(),
            'duration_days' => $this->duration_days,
            'price' => $this->price,
            'clipping_quota' => $this->clipping_quota,
            'download_limit_per_edition' => $this->download_limit_per_edition,
            'download_limit_per_day' => $this->download_limit_per_day,
            'includes_free_ads' => $this->includesFreeAds(),
            'includes_epaper' => $this->includesEpaper(),
            'is_recommended' => $this->is_recommended,
        ];
    }
}