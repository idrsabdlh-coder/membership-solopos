<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function validateCode(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'voucher_code' => ['required', 'string', 'max:100'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $quote = $orders->quote($plan, $validated['voucher_code']);

        return response()->json(['data' => $quote]);
    }
}
