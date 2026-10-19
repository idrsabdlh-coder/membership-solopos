<?php

// app/Http/Controllers/Api/PlanController.php

namespace App\Http\Controllers\Api;

use App\Enums\BillingPeriod;
use App\Enums\PlanType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(PlanType::class)],
            'period' => ['nullable', Rule::enum(BillingPeriod::class)],
        ]);

        $plans = Plan::query()
            ->where('is_active', true)
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('period'), fn ($q, $v) => $q->where('billing_period', $v))
            ->orderBy('duration_days')
            ->orderBy('type')
            ->get();

        return PlanResource::collection($plans)
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
