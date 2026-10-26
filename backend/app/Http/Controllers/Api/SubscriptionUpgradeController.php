<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Services\SubscriptionUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionUpgradeController extends Controller
{
    public function quote(Request $request, SubscriptionUpgradeService $upgrades): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'voucher_code' => ['prohibited'],
            'voucher_codes' => ['prohibited'],
            'voucher_id' => ['prohibited'],
            'upgrade_credit' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount' => ['prohibited'],
            'total' => ['prohibited'],
        ]);

        $quote = $upgrades->quote($request->user(), (int) $validated['plan_id']);

        return response()->json([
            'data' => [
                'current_subscription' => SubscriptionResource::make($quote['current_subscription'])->resolve($request),
                'target_plan' => PlanResource::make($quote['target_plan'])->resolve($request),
                'current_plan_price' => $quote['current_plan_price'],
                'subtotal' => $quote['subtotal'],
                'upgrade_credit' => $quote['upgrade_credit'],
                'total' => $quote['total'],
                'remaining_seconds' => $quote['remaining_seconds'],
                'total_term_seconds' => $quote['total_term_seconds'],
            ],
        ]);
    }

    public function store(Request $request, SubscriptionUpgradeService $upgrades): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'voucher_code' => ['prohibited'],
            'voucher_codes' => ['prohibited'],
            'voucher_id' => ['prohibited'],
            'upgrade_credit' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'discount' => ['prohibited'],
            'total' => ['prohibited'],
        ]);

        $order = $upgrades->create($request->user(), (int) $validated['plan_id']);

        return OrderResource::make($order)->response()->setStatusCode(201);
    }
}
