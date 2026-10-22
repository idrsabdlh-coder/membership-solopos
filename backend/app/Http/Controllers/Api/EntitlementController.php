<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = Subscription::query()
            ->active()
            ->where('user_id', $request->user()->getKey())
            ->with('plan')
            ->orderByDesc('end_date')
            ->first();

        $plan = $subscription?->plan;
        $entitlements = [
            'free_ads' => $plan?->includesFreeAds() ?? false,
            'epaper' => $plan?->includesEpaper() ?? false,
            'clipping_quota' => $plan?->clipping_quota ?? 0,
            'download_limit_per_edition' => $plan?->download_limit_per_edition ?? 0,
            'download_limit_per_day' => $plan?->download_limit_per_day ?? 0,
        ];

        return response()
            ->json([
                'data' => [
                    ...$entitlements,
                    'subscription' => $subscription ? [
                        'id' => $subscription->id,
                        'starts_at' => $subscription->start_date->timezone('Asia/Jakarta')->toIso8601String(),
                        'expires_at' => $subscription->end_date->timezone('Asia/Jakarta')->toIso8601String(),
                    ] : null,
                    'plan' => $plan ? [
                        'id' => $plan->id,
                        'type' => $plan->type->value,
                        'name' => $plan->name,
                    ] : null,
                    'checked_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
                ],
            ])
            ->header('Cache-Control', 'private, no-store');
    }
}
