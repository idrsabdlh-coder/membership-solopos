<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClippingResource;
use App\Models\Clipping;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClippingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $userId = (int) $request->user()->getKey();
        $now = now();

        $clippings = Clipping::query()
            ->where('user_id', $userId)
            ->whereHas('subscription', function (Builder $query) use ($now): void {
                $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now->copy()->subDays(90));
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('article_ref', 'like', '%'.$search.'%');
                });
            })
            ->with('subscription.plan')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        $currentSubscription = $this->currentClippingSubscription($userId);
        $quota = $currentSubscription?->plan->clipping_quota ?? 0;
        $used = $currentSubscription
            ? Clipping::query()->where('subscription_id', $currentSubscription->id)->count()
            : 0;

        return response()->json([
            'data' => ClippingResource::collection($clippings->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $clippings->currentPage(),
                'last_page' => $clippings->lastPage(),
                'per_page' => $clippings->perPage(),
                'total' => $clippings->total(),
                'quota' => [
                    'limit' => $quota,
                    'used' => $used,
                    'remaining' => max(0, $quota - $used),
                    'read_only' => $currentSubscription === null,
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_ref' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ]);
        $userId = (int) $request->user()->getKey();

        $clipping = DB::transaction(function () use ($request, $userId, $validated): Clipping {
            $request->user()->newQuery()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $subscription = $this->currentClippingSubscription($userId, lock: true);

            if (! $subscription || $subscription->plan->clipping_quota < 1) {
                abort(response()->json([
                    'error' => [
                        'code' => 'clipping_not_entitled',
                        'message' => 'Paket aktif Anda tidak memiliki akses clipping.',
                    ],
                ], 403));
            }

            $duplicate = Clipping::query()
                ->where('user_id', $userId)
                ->where('article_ref', $validated['article_ref'])
                ->exists();

            if ($duplicate) {
                abort(response()->json([
                    'error' => [
                        'code' => 'duplicate_article',
                        'message' => 'Artikel ini sudah tersimpan di clipping Anda.',
                    ],
                ], 409));
            }

            $used = Clipping::query()
                ->where('subscription_id', $subscription->id)
                ->count();
            $quota = $subscription->plan->clipping_quota;

            if ($used >= $quota) {
                abort(response()->json([
                    'error' => [
                        'code' => 'clipping_quota_exceeded',
                        'message' => 'Kuota clipping paket Anda sudah habis.',
                    ],
                ], 409));
            }

            return Clipping::query()->create([
                'user_id' => $userId,
                'subscription_id' => $subscription->id,
                ...$validated,
            ])->load('subscription.plan');
        }, attempts: 3);

        $used = Clipping::query()->where('subscription_id', $clipping->subscription_id)->count();
        $quota = $clipping->subscription->plan->clipping_quota;

        return response()->json([
            'data' => ClippingResource::make($clipping)->resolve($request),
            'meta' => [
                'quota' => [
                    'limit' => $quota,
                    'used' => $used,
                    'remaining' => max(0, $quota - $used),
                ],
            ],
        ], 201);
    }

    public function destroy(Request $request, string $clipping): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $clippingModel = Clipping::query()
            ->where('user_id', $userId)
            ->with('subscription')
            ->findOrFail($clipping);
        $activeSubscription = $this->currentClippingSubscription($userId);

        if (! $activeSubscription || $clippingModel->subscription_id !== $activeSubscription->id) {
            abort(response()->json([
                'error' => [
                    'code' => 'clipping_read_only',
                    'message' => 'Clipping hanya dapat diubah atau dihapus selama paket terkait aktif.',
                ],
            ], 409));
        }

        $clippingModel->delete();

        return response()->json(null, 204);
    }

    private function currentClippingSubscription(int $userId, bool $lock = false): ?Subscription
    {
        $query = Subscription::query()
            ->active()
            ->where('user_id', $userId)
            ->whereHas('plan', fn (Builder $query) => $query->where('clipping_quota', '>', 0))
            ->with('plan')
            ->orderByDesc('end_date');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
