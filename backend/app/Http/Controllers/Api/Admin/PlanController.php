<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PlanType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPlanResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::enum(PlanType::class)],
            'billing_period' => ['sometimes', Rule::enum(BillingPeriod::class)],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $plans = Plan::query()
            ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($validated['billing_period'] ?? null, fn (Builder $query, string $period) => $query->where('billing_period', $period))
            ->when(array_key_exists('is_active', $validated), fn (Builder $query) => $query->where('is_active', $validated['is_active']))
            ->orderBy('duration_days')
            ->orderBy('type')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => AdminPlanResource::collection($plans->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function store(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $type = $request->input('type');
        $planType = is_string($type) ? $type : '';

        $validated = $request->validate([
            'type' => ['required', Rule::enum(PlanType::class)],
            'name' => ['required', 'string', 'max:255'],
            'billing_period' => [
                'required',
                Rule::enum(BillingPeriod::class),
                Rule::unique('plans', 'billing_period')->where(
                    fn (QueryBuilder $query) => $query->where('type', $planType)
                ),
            ],
            'price' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'clipping_quota' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'download_limit_per_edition' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'download_limit_per_day' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'is_recommended' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'duration_days' => ['prohibited'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $plan = DB::transaction(function () use ($validated, $actor, $request, $audit): Plan {
            $plan = new Plan;
            $plan->forceFill([
                ...$validated,
                'duration_days' => $this->durationDays(BillingPeriod::from($validated['billing_period'])),
                'clipping_quota' => $validated['clipping_quota'] ?? 0,
                'download_limit_per_edition' => $validated['download_limit_per_edition'] ?? 0,
                'download_limit_per_day' => $validated['download_limit_per_day'] ?? 0,
                'is_recommended' => $validated['is_recommended'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ])->save();
            $plan->refresh();

            $audit->record($actor, $request, 'plan.created', 'plan', $plan->id, [
                'after' => $this->auditablePlanValues($plan),
            ]);

            return $plan;
        });

        return response()->json([
            'data' => AdminPlanResource::make($plan)->resolve($request),
        ], 201);
    }

    public function show(Request $request, Plan $plan): JsonResponse
    {
        return response()->json([
            'data' => AdminPlanResource::make($plan)->resolve($request),
        ]);
    }

    public function update(Request $request, Plan $plan, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'integer', 'min:1', 'max:4294967295'],
            'clipping_quota' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            'download_limit_per_edition' => ['sometimes', 'required', 'integer', 'min:0', 'max:255'],
            'download_limit_per_day' => ['sometimes', 'required', 'integer', 'min:0', 'max:255'],
            'is_recommended' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'type' => ['prohibited'],
            'billing_period' => ['prohibited'],
            'duration_days' => ['prohibited'],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'plan' => ['Setidaknya satu atribut paket harus diperbarui.'],
            ]);
        }

        /** @var User $actor */
        $actor = $request->user();
        $plan = DB::transaction(function () use ($plan, $validated, $actor, $request, $audit): Plan {
            $lockedPlan = Plan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $before = $this->auditablePlanValues($lockedPlan);
            $lockedPlan->forceFill($validated)->save();
            $lockedPlan->refresh();

            $audit->record($actor, $request, 'plan.updated', 'plan', $lockedPlan->id, [
                'before' => $before,
                'after' => $this->auditablePlanValues($lockedPlan),
            ]);

            return $lockedPlan;
        });

        return response()->json([
            'data' => AdminPlanResource::make($plan)->resolve($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditablePlanValues(Plan $plan): array
    {
        return [
            'name' => $plan->name,
            'price' => $plan->price,
            'clipping_quota' => $plan->clipping_quota,
            'download_limit_per_edition' => $plan->download_limit_per_edition,
            'download_limit_per_day' => $plan->download_limit_per_day,
            'is_recommended' => $plan->is_recommended,
            'is_active' => $plan->is_active,
        ];
    }

    private function durationDays(BillingPeriod $period): int
    {
        return match ($period) {
            BillingPeriod::Weekly => 7,
            BillingPeriod::Monthly => 30,
            BillingPeriod::Yearly => 365,
        };
    }
}
