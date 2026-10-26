<?php

namespace App\Http\Controllers\Api;

use App\Enums\EditionStatus;
use App\Enums\EpaperDownloadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpaperDownloadResource;
use App\Models\EpaperDownload;
use App\Models\EpaperEdition;
use App\Models\User;
use App\Services\EpaperAccessService;
use App\Services\EpaperDownloadService;
use App\Services\EpaperPrivateStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EpaperDownloadController extends Controller
{
    public function store(
        Request $request,
        EpaperEdition $edition,
        EpaperDownloadService $downloads,
        EpaperAccessService $access
    ): JsonResponse {
        abort_unless($edition->status === EditionStatus::Published, 404);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $download = $downloads->request($user, $edition, $request, $access);

        return response()->json([
            'data' => EpaperDownloadResource::make($download->load('edition'))->resolve($request),
        ], 202)->header('Cache-Control', 'private, no-store');
    }

    public function index(
        Request $request,
        EpaperAccessService $access,
        EpaperDownloadService $downloadService
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $downloads = EpaperDownload::query()
            ->with('edition')
            ->where('user_id', $user->id)
            ->orderByDesc('requested_at')
            ->paginate(20);
        [$dayStart, $dayEnd] = $this->todayRange();
        $usedToday = $downloadService->usedQuota($user, $dayStart, $dayEnd);
        $subscription = $access->activeEpaperSubscriptions($user)->first();
        $dailyLimit = $subscription === null
            ? 0
            : ($subscription->plan->download_limit_per_day ?: (int) config('epaper.default_download_limit_per_day'));

        return response()->json([
            'data' => EpaperDownloadResource::collection($downloads->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $downloads->currentPage(),
                'last_page' => $downloads->lastPage(),
                'per_page' => $downloads->perPage(),
                'total' => $downloads->total(),
                'quota' => [
                    'per_day_limit' => $dailyLimit,
                    'used_today' => $usedToday,
                    'remaining_today' => max(0, $dailyLimit - $usedToday),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, EpaperDownload $download): JsonResponse
    {
        abort_unless($download->user_id === $request->user()?->id, 404);
        $download->load('edition');

        return response()->json([
            'data' => EpaperDownloadResource::make($download)->resolve($request),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function file(EpaperDownload $download, EpaperPrivateStorage $storage)
    {
        if (! in_array($download->status, [EpaperDownloadStatus::Ready, EpaperDownloadStatus::Downloaded], true)
            || $download->expires_at === null
            || $download->generated_pdf_path === null
            || $download->expires_at->isPast()) {
            return response()->json([
                'error' => [
                    'code' => 'epaper_download_expired',
                    'message' => 'Tautan unduhan telah kedaluwarsa. Ajukan unduhan baru.',
                ],
            ], 410);
        }

        $disk = $storage->disk();
        $stream = $disk->readStream($download->generated_pdf_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read the generated E-Paper PDF.');
        }

        if ($download->downloaded_at === null) {
            $download->forceFill([
                'status' => EpaperDownloadStatus::Downloaded,
                'downloaded_at' => now(),
            ])->save();
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $download->file_name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function todayRange(): array
    {
        $start = CarbonImmutable::now('Asia/Jakarta')->startOfDay()->utc();

        return [$start, $start->addDay()];
    }
}
