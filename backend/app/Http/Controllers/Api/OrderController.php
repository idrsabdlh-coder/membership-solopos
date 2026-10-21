<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'voucher_code' => ['nullable', 'string', 'max:100'],
            'voucher_codes' => ['prohibited'],
            'upgrade_credit' => ['prohibited'],
        ]);

        $order = $orders->create(
            $request->user(),
            (int) $validated['plan_id'],
            $validated['voucher_code'] ?? null
        );

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $order, OrderService $orders): OrderResource
    {
        $order = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['plan', 'voucher'])
            ->findOrFail($order);

        $orders->expirePendingOrders($order->id);

        return OrderResource::make($order->fresh(['plan', 'voucher']));
    }
}
