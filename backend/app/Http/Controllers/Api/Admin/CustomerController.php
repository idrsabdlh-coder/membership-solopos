<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCustomerResource;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $staffEmails = array_values(array_unique([
            ...config('backoffice.admin_emails', []),
            ...config('backoffice.staff_emails', []),
        ]));
        $customers = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'role', 'created_at'])
            ->where('role', 'member')
            ->when($staffEmails !== [], fn (Builder $query) => $query->whereNotIn(DB::raw('LOWER(email)'), $staffEmails))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.Str::lower(trim($search)).'%';
                $query->where(function (Builder $match) use ($term): void {
                    $match->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->withCount('subscriptions')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'customer.listed', 'customer', null, [
            'result_count' => $customers->count(),
            'page' => $customers->currentPage(),
        ]);

        return response()->json([
            'data' => AdminCustomerResource::collection($customers->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(Request $request, User $customer, BackofficeAuditService $audit): JsonResponse
    {
        if ($customer->isBackofficeStaff()) {
            abort(404);
        }

        $customer->loadCount('subscriptions');

        /** @var User $actor */
        $actor = $request->user();
        $audit->record($actor, $request, 'customer.viewed', 'customer', $customer->id);

        return response()->json([
            'data' => AdminCustomerResource::make($customer)->resolve($request),
        ]);
    }
}
