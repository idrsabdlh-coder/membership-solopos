<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyDataRequest;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrivacyDataRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_review', 'fulfilled', 'rejected'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $requests = PrivacyDataRequest::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->where('type', 'erasure')
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('requested_at')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $requests->getCollection()->map(fn (PrivacyDataRequest $dataRequest): array => $this->resource($dataRequest)),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(
        Request $request,
        PrivacyDataRequest $privacyDataRequest,
        BackofficeAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'fulfilled', 'rejected'])],
            'resolution_note' => ['required_if:status,fulfilled,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($privacyDataRequest, $validated, $actor, $request, $audit): PrivacyDataRequest {
            $lockedRequest = PrivacyDataRequest::query()
                ->whereKey($privacyDataRequest->id)
                ->where('type', 'erasure')
                ->lockForUpdate()
                ->firstOrFail();

            $allowedTransitions = match ($lockedRequest->status) {
                'pending' => ['in_review', 'rejected'],
                'in_review' => ['fulfilled', 'rejected'],
                default => [],
            };

            if (! in_array($validated['status'], $allowedTransitions, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Perubahan status permintaan ini tidak diizinkan.'],
                ]);
            }

            $before = $lockedRequest->status;
            $lockedRequest->status = $validated['status'];
            $lockedRequest->reviewed_by = $actor->getKey();
            $lockedRequest->resolution_note = $validated['resolution_note'] ?? null;
            $lockedRequest->resolved_at = in_array($validated['status'], ['fulfilled', 'rejected'], true)
                ? now()
                : null;
            $lockedRequest->save();
            $lockedRequest->load(['user:id,name,email', 'reviewer:id,name,email']);

            $audit->record($actor, $request, 'privacy.erasure_request.status_changed', 'privacy_data_request', $lockedRequest->id, [
                'before' => ['status' => $before],
                'after' => ['status' => $lockedRequest->status],
            ]);

            return $lockedRequest;
        });

        return response()->json([
            'data' => $this->resource($result),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(PrivacyDataRequest $dataRequest): array
    {
        return [
            'id' => $dataRequest->id,
            'type' => $dataRequest->type,
            'status' => $dataRequest->status,
            'reason' => $dataRequest->reason,
            'resolution_note' => $dataRequest->resolution_note,
            'requested_at' => $dataRequest->requested_at->toIso8601String(),
            'resolved_at' => $dataRequest->resolved_at?->toIso8601String(),
            'customer' => $dataRequest->user ? [
                'id' => $dataRequest->user->id,
                'name' => $dataRequest->user->name,
                'email' => $dataRequest->user->email,
            ] : null,
            'reviewer' => $dataRequest->reviewer ? [
                'id' => $dataRequest->reviewer->id,
                'name' => $dataRequest->reviewer->name,
                'email' => $dataRequest->reviewer->email,
            ] : null,
        ];
    }
}
