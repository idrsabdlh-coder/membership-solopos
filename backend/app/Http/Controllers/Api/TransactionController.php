<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionHistoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->with([
                'plan',
                'payments' => fn ($query) => $query->latest('id'),
                'subscription',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => TransactionResource::collection($transactions->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function subscriptionHistory(
        Request $request,
        SubscriptionLifecycleService $lifecycle
    ): JsonResponse {
        $userId = (int) $request->user()->getKey();
        $lifecycle->transitionStatuses($userId);
        $subscriptions = Subscription::query()
            ->where('user_id', $userId)
            ->with([
                'plan',
                'sourceOrder.payments' => fn ($query) => $query->latest('id'),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => SubscriptionHistoryResource::collection($subscriptions->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function invoice(Request $request, string $order, InvoiceService $invoices): StreamedResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->with([
                'plan',
                'user',
                'voucher',
                'subscription',
                'payments' => fn ($query) => $query->latest('id'),
            ])
            ->findOrFail($order);

        $content = $invoices->generate($order);

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            'invoice-'.$order->order_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
