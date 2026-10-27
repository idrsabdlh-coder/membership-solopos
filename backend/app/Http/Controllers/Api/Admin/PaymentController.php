<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'method' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $payments = Payment::query()
            ->with([
                'order:id,user_id,order_number,status',
                'order.user:id,name,email',
            ])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['method'] ?? null, fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'payment.listed', 'payment', null, [
            'result_count' => $payments->count(),
            'page' => $payments->currentPage(),
            'status' => $validated['status'] ?? null,
            'method' => $validated['method'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        return response()->json([
            'data' => AdminPaymentResource::collection($payments->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
