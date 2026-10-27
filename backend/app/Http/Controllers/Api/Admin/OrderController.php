<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'payment_status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->with([
                'user:id,name,email',
                'plan:id,type,name,billing_period',
                'payments' => fn (HasMany $query) => $query->latest('id')->limit(1),
                'subscription',
            ])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['payment_status'] ?? null, fn (Builder $query, string $status) => $query->whereHas(
                'payments',
                fn (Builder $payments) => $payments->where('status', $status)
            ))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.strtolower(trim($search)).'%';
                $query->where(function (Builder $match) use ($term): void {
                    $match->whereRaw('LOWER(order_number) LIKE ?', [$term])
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'order.listed', 'order', null, [
            'result_count' => $orders->count(),
            'page' => $orders->currentPage(),
            'status' => $validated['status'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        return response()->json([
            'data' => AdminOrderResource::collection($orders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
