<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'billing_period' => $this->billing_period->value,
            'duration_days' => $this->duration_days,
            'price' => $this->price,
            'clipping_quota' => $this->clipping_quota,
            'download_limit_per_edition' => $this->download_limit_per_edition,
            'download_limit_per_day' => $this->download_limit_per_day,
            'is_recommended' => $this->is_recommended,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
            'updated_at' => $this->updated_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
