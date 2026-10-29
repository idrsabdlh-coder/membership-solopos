<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivacyDataRequest;
use App\Models\User;
use App\Models\UserConsentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrivacyController extends Controller
{
    public function consents(Request $request): JsonResponse
    {
        $user = $request->user();
        $versions = config('privacy.consent_versions', []);
        $history = $user->consentRecords()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();
        $latestRecords = $history
            ->unique('type')
            ->keyBy('type');

        $consents = [];
        foreach ($versions as $type => $version) {
            $latest = $latestRecords->get($type);
            $consents[] = [
                'type' => $type,
                'current_version' => $version,
                'accepted' => $latest !== null
                    && $latest->version === $version
                    && $latest->accepted,
                'recorded_at' => $latest?->recorded_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'data' => $consents,
            'history' => $history->map(fn (UserConsentRecord $record): array => [
                'type' => $record->type,
                'version' => $record->version,
                'accepted' => $record->accepted,
                'recorded_at' => $record->recorded_at->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $versions = config('privacy.consent_versions', []);

        if (! is_string($type) || ! array_key_exists($type, $versions)) {
            throw ValidationException::withMessages([
                'type' => ['Jenis persetujuan tidak tersedia.'],
            ]);
        }

        $currentVersion = $versions[$type];
        if (! is_string($currentVersion) || $currentVersion === '') {
            return response()->json([
                'error' => [
                    'code' => 'privacy_policy_not_configured',
                    'message' => 'Versi dokumen privasi belum dikonfigurasi oleh pengelola.',
                ],
            ], 503);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([$type])],
            'version' => ['required', 'string', 'max:100', Rule::in([$currentVersion])],
            'accepted' => ['required', 'boolean'],
        ]);

        $record = UserConsentRecord::query()->create([
            'user_id' => $request->user()->getKey(),
            'type' => $validated['type'],
            'version' => $validated['version'],
            'accepted' => $validated['accepted'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'recorded_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'type' => $record->type,
                'version' => $record->version,
                'accepted' => $record->accepted,
                'recorded_at' => $record->recorded_at->toIso8601String(),
            ],
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request): JsonResponse
    {
        $this->ensureDatabaseSessionStore();
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'subscriptions.plan',
            'orders.plan',
            'orders.voucher',
            'orders.payments',
            'orders.subscription',
            'clippings',
            'consentRecords' => fn ($query) => $query->orderBy('recorded_at'),
            'privacyDataRequests.reviewer:id,name,email',
            'privacyDataRequests' => fn ($query) => $query->orderBy('requested_at'),
            'notificationDeliveries' => fn ($query) => $query->orderBy('created_at'),
            'epaperDownloads.edition',
        ]);

        $sessions = DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->getKey())
            ->orderBy('last_activity')
            ->get(['ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => now()->setTimestamp((int) $session->last_activity)
                    ->timezone('Asia/Jakarta')
                    ->toIso8601String(),
            ]);

        $export = [
            'generated_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->backofficeRole()->value,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'whatsapp' => $user->whatsapp,
                'avatar_path' => $user->avatar_path,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'consents' => $user->consentRecords->map(fn (UserConsentRecord $record): array => [
                'type' => $record->type,
                'version' => $record->version,
                'accepted' => $record->accepted,
                'ip_address' => $record->ip_address,
                'user_agent' => $record->user_agent,
                'recorded_at' => $record->recorded_at->toIso8601String(),
            ]),
            'subscriptions' => $user->subscriptions->map(fn ($subscription): array => [
                'plan' => $subscription->plan->name,
                'status' => $subscription->status->value,
                'start_date' => $subscription->start_date?->toIso8601String(),
                'end_date' => $subscription->end_date?->toIso8601String(),
                'source_order_id' => $subscription->source_order_id,
                'created_at' => $subscription->created_at->toIso8601String(),
            ]),
            'orders' => $user->orders->map(fn ($order): array => [
                'order_number' => $order->order_number,
                'plan' => $order->plan->name,
                'voucher_code' => $order->voucher?->code,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'upgrade_credit' => $order->upgrade_credit,
                'total' => $order->total,
                'status' => $order->status->value,
                'expires_at' => $order->expires_at?->toIso8601String(),
                'created_at' => $order->created_at->toIso8601String(),
                'payments' => $order->payments->map(fn ($payment): array => [
                    'gateway' => $payment->gateway,
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ]),
            ]),
            'clippings' => $user->clippings->map(fn ($clipping): array => [
                'article_ref' => $clipping->article_ref,
                'title' => $clipping->title,
                'url' => $clipping->url,
                'created_at' => $clipping->created_at->toIso8601String(),
            ]),
            'epaper_downloads' => $user->epaperDownloads->map(fn ($download): array => [
                'edition' => $download->edition->edition_number.'/'.$download->edition->edition_year,
                'watermark_hash' => $download->watermark_hash,
                'file_name' => $download->file_name,
                'ip_address' => $download->ip_address,
                'user_agent' => $download->user_agent,
                'downloaded_at' => $download->downloaded_at?->toIso8601String(),
            ]),
            'notification_deliveries' => $user->notificationDeliveries->map(fn ($delivery): array => [
                'type' => $delivery->type,
                'recipient' => $delivery->recipient,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'failure_reason' => $delivery->failure_reason,
                'sent_at' => $delivery->sent_at?->toIso8601String(),
            ]),
            'privacy_data_requests' => $user->privacyDataRequests->map(fn (PrivacyDataRequest $dataRequest): array => [
                'type' => $dataRequest->type,
                'status' => $dataRequest->status,
                'reason' => $dataRequest->reason,
                'resolution_note' => $dataRequest->resolution_note,
                'requested_at' => $dataRequest->requested_at->toIso8601String(),
                'resolved_at' => $dataRequest->resolved_at?->toIso8601String(),
                'reviewer' => $dataRequest->reviewer ? [
                    'name' => $dataRequest->reviewer->name,
                    'email' => $dataRequest->reviewer->email,
                ] : null,
            ]),
            'sessions' => $sessions,
        ];

        return response()->json($export)
            ->header('Cache-Control', 'private, no-store')
            ->header('Content-Disposition', 'attachment; filename="membership-personal-data.json"');
    }

    public function deletionRequests(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->privacyDataRequests()
                ->orderByDesc('requested_at')
                ->get()
                ->map(fn (PrivacyDataRequest $dataRequest): array => [
                    'id' => $dataRequest->id,
                    'type' => $dataRequest->type,
                    'status' => $dataRequest->status,
                    'reason' => $dataRequest->reason,
                    'resolution_note' => $dataRequest->resolution_note,
                    'requested_at' => $dataRequest->requested_at->toIso8601String(),
                    'resolved_at' => $dataRequest->resolved_at?->toIso8601String(),
                ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $dataRequest = DB::transaction(function () use ($user, $validated): PrivacyDataRequest {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $existing = PrivacyDataRequest::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'erasure')
                ->whereIn('status', ['pending', 'in_review'])
                ->first();

            if ($existing) {
                return $existing;
            }

            return PrivacyDataRequest::query()->create([
                'user_id' => $user->getKey(),
                'type' => 'erasure',
                'status' => 'pending',
                'reason' => $validated['reason'] ?? null,
                'requested_at' => now(),
            ]);
        }, attempts: 3);

        return response()->json([
            'data' => [
                'id' => $dataRequest->id,
                'type' => $dataRequest->type,
                'status' => $dataRequest->status,
                'requested_at' => $dataRequest->requested_at->toIso8601String(),
                'message' => 'Permintaan dicatat untuk ditinjau. Data tidak dihapus otomatis sebelum pemeriksaan kewajiban retensi dan persetujuan pengelola.',
            ],
        ], 202)->header('Cache-Control', 'private, no-store');
    }

    private function ensureDatabaseSessionStore(): void
    {
        abort_unless(
            config('session.driver') === 'database'
                && (
                    config('session.connection') === null
                    || config('session.connection') === config('database.default')
                ),
            503,
            'Ekspor data pribadi memerlukan session driver database pada koneksi utama aplikasi.'
        );
    }
}
