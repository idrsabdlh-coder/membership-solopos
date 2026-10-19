<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    private const MAX_ACTIVE_SESSIONS = 2;

    public function index(Request $request): JsonResponse
    {
        $this->ensureDatabaseSessionStore();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        $sessions = $this->activeSessions($request->user(), $currentSessionId);

        return response()->json([
            'data' => $sessions,
            'meta' => ['max_active_sessions' => self::MAX_ACTIVE_SESSIONS],
        ]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        $this->ensureDatabaseSessionStore();

        abort_unless(preg_match('/^[a-f0-9]{64}$/', $session) === 1, 404);

        $user = $request->user();
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        $table = $this->sessionTable();
        $row = $table
            ->where('user_id', $user->getKey())
            ->get(['id'])
            ->first(fn (object $row): bool => hash_equals(hash('sha256', $row->id), $session));

        if (! $row) {
            abort(404);
        }

        abort_if($currentSessionId && hash_equals($currentSessionId, $row->id), 422, 'Gunakan tombol keluar untuk mengakhiri sesi perangkat ini.');

        $this->sessionTable()->where('id', $row->id)->delete();

        return response()->json([], 204);
    }

    public function enforceLimit(Request $request, User $user): bool
    {
        $this->ensureDatabaseSessionStore();

        $currentSessionId = $request->session()->getId();

        return DB::transaction(function () use ($user, $currentSessionId): bool {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $cutoff = now()->subMinutes((int) config('session.lifetime'))->timestamp;

            $this->sessionTable()->where('id', $currentSessionId)
                ->update(['user_id' => $user->getKey()]);

            $this->sessionTable()->where('user_id', $user->getKey())
                ->where('last_activity', '<', $cutoff)
                ->delete();

            $sessions = $this->sessionTable()->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get(['id', 'last_activity'])
                ->sort(function (object $left, object $right) use ($currentSessionId): int {
                    $leftIsCurrent = hash_equals($currentSessionId, $left->id);
                    $rightIsCurrent = hash_equals($currentSessionId, $right->id);

                    if ($leftIsCurrent !== $rightIsCurrent) {
                        return $leftIsCurrent ? -1 : 1;
                    }

                    return $right->last_activity <=> $left->last_activity;
                })
                ->values();

            $revoked = $sessions->count() > self::MAX_ACTIVE_SESSIONS;

            if ($revoked) {
                $this->sessionTable()->whereIn('id', $sessions
                    ->slice(self::MAX_ACTIVE_SESSIONS)
                    ->pluck('id'))
                    ->delete();
            }

            return $revoked;
        }, attempts: 3);
    }

    private function activeSessions(User $user, ?string $currentSessionId): Collection
    {
        $cutoff = now()->subMinutes((int) config('session.lifetime'))->timestamp;

        return $this->sessionTable()
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', $cutoff)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'id' => hash('sha256', $session->id),
                'device' => $this->deviceName($session->user_agent),
                'ip_address' => $session->ip_address,
                'last_activity' => now()->setTimestamp((int) $session->last_activity)
                    ->timezone('Asia/Jakarta')
                    ->toIso8601String(),
                'is_current' => $currentSessionId !== null && hash_equals($currentSessionId, $session->id),
            ])
            ->sort(function (array $left, array $right): int {
                if ($left['is_current'] !== $right['is_current']) {
                    return $left['is_current'] ? -1 : 1;
                }

                return $right['last_activity'] <=> $left['last_activity'];
            })
            ->values();
    }

    private function deviceName(?string $userAgent): string
    {
        $agent = Str::lower($userAgent ?? '');
        $browser = match (true) {
            str_contains($agent, 'edg/') => 'Microsoft Edge',
            str_contains($agent, 'opr/') || str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'chrome/') || str_contains($agent, 'crios/') => 'Chrome',
            str_contains($agent, 'safari/') => 'Safari',
            default => 'Browser',
        };
        $platform = match (true) {
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad'), str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'macintosh'), str_contains($agent, 'mac os') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'perangkat tidak dikenal',
        };

        return $browser.' di '.$platform;
    }

    public function ensureDatabaseSessionStore(): void
    {
        abort_unless(
            config('session.driver') === 'database'
                && (
                    config('session.connection') === null
                    || config('session.connection') === config('database.default')
                ),
            503,
            'Pengelolaan sesi memerlukan session driver database pada koneksi utama aplikasi.'
        );
    }

    private function sessionTable()
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table'));
    }
}
