<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->timezone('Asia/Jakarta')->toIso8601String(),
            'end_date' => $this->end_date?->timezone('Asia/Jakarta')->toIso8601String(),
            'days_remaining' => $this->status->value === 'active' && $this->end_date?->isFuture()
                ? (int) $now->diffInDays($this->end_date)
                : 0,
            'source_order' => $this->whenLoaded('sourceOrder', fn () => $this->sourceOrder?->order_number),
            'plan' => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'type' => $this->plan->type->value,
                'billing_period' => $this->plan->billing_period->value,
                'duration_days' => $this->plan->duration_days,
                'entitlements' => [
                    'free_ads' => $this->plan->includesFreeAds(),
                    'epaper' => $this->plan->includesEpaper(),
                    'clipping_quota' => $this->plan->clipping_quota,
                    'download_limit_per_edition' => $this->plan->download_limit_per_edition,
                    'download_limit_per_day' => $this->plan->download_limit_per_day,
                ],
            ],
        ];
    }
}
