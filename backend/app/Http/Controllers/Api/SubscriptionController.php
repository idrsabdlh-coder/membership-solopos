<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request, SubscriptionLifecycleService $subscriptions): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $subscriptions->transitionStatuses($userId);

        $current = Subscription::query()
            ->active()
            ->where('user_id', $userId)
            ->with(['plan', 'sourceOrder'])
            ->orderByDesc('end_date')
            ->first();

        $history = Subscription::query()
            ->where('user_id', $userId)
            ->with(['plan', 'sourceOrder'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => [
                'current' => $current ? SubscriptionResource::make($current)->resolve($request) : null,
                'history' => SubscriptionResource::collection($history->getCollection())->resolve($request),
            ],
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }
}
