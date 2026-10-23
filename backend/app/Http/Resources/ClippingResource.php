<?php

namespace App\Http\Resources;

use App\Enums\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClippingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscription = $this->subscription;
        $readOnly = ! $subscription
            || $subscription->status !== SubscriptionStatus::Active
            || ! $subscription->start_date->isPast()
            || ! $subscription->end_date->isFuture()
            || $subscription->plan->clipping_quota < 1;

        return [
            'id' => $this->id,
            'article_ref' => $this->article_ref,
            'title' => $this->title,
            'url' => $this->url,
            'subscription_id' => $this->subscription_id,
            'subscription_end_date' => $this->subscription?->end_date?->timezone('Asia/Jakarta')->toIso8601String(),
            'read_only' => $readOnly,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
