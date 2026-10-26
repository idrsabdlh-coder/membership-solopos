<?php

namespace App\Services;

use App\Enums\EpaperDownloadStatus;
use App\Jobs\GenerateEpaperDownload;
use App\Models\EpaperDownload;
use App\Models\EpaperEdition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class EpaperDownloadService
{
    public function __construct(private readonly EpaperPrivateStorage $storage) {}

    public function request(
        User $user,
        EpaperEdition $edition,
        Request $request,
        EpaperAccessService $access
    ): EpaperDownload {
        $download = DB::transaction(function () use ($user, $edition, $request, $access): EpaperDownload {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $subscription = $access->subscriptionForEdition($user, $edition, lock: true);

            if ($subscription === null) {
                throw new HttpResponseException(response()->json([
                    'error' => [
                        'code' => 'epaper_subscription_required',
                        'message' => 'Langganan aktif yang mencakup E-Paper diperlukan untuk mengunduh edisi ini.',
                    ],
                ], 403));
            }

            $now = CarbonImmutable::now('Asia/Jakarta');
            $dayStart = $now->startOfDay()->utc();
            $dayEnd = $dayStart->addDay();
            $dailyLimit = $subscription->plan->download_limit_per_day
                ?: (int) config('epaper.default_download_limit_per_day');
            $editionLimit = $subscription->plan->download_limit_per_edition
                ?: (int) config('epaper.default_download_limit_per_edition');
            $dailyUsed = $this->quotaQuery($user, $dayStart, $dayEnd)->count();
            $editionUsed = $this->quotaQuery($user, $dayStart, $dayEnd)
                ->where('edition_id', $edition->id)
                ->count();

            if ($dailyUsed >= $dailyLimit || $editionUsed >= $editionLimit) {
                throw new HttpResponseException(response()->json([
                    'error' => [
                        'code' => 'download_limit_reached',
                        'message' => 'Batas unduhan untuk hari ini atau edisi ini telah tercapai.',
                    ],
                    'meta' => [
                        'per_day_limit' => $dailyLimit,
                        'used_today' => $dailyUsed,
                        'per_edition_limit' => $editionLimit,
                        'used_for_edition' => $editionUsed,
                    ],
                ], 409));
            }

            $hash = bin2hex(random_bytes(16));
            $editionLabel = sprintf('%03d-%d', $edition->edition_number, $edition->edition_year);

            return EpaperDownload::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'edition_id' => $edition->id,
                'watermark_hash' => $hash,
                'file_name' => 'solopos-epaper-'.$editionLabel.'.pdf',
                'generated_pdf_path' => 'epaper/generated/'.$hash.'.pdf',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'status' => EpaperDownloadStatus::Processing,
                'requested_at' => now(),
                'downloaded_at' => null,
            ]);
        });

        GenerateEpaperDownload::dispatch($download->id)->afterCommit();

        return $download;
    }

    public function generate(EpaperDownload $download): void
    {
        $download->loadMissing(['edition', 'user']);
        $disk = $this->storage->disk();
        $source = $this->storage->copyToTemporaryFile($download->edition->master_pdf_path, 'epaper-master-');
        $output = tempnam(sys_get_temp_dir(), 'epaper-download-');
        if ($output === false) {
            unlink($source);
            throw new RuntimeException('Unable to allocate a temporary E-Paper download file.');
        }

        try {
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false, 0);
            $pageCount = $pdf->setSourceFile($source);
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
                $pdf->SetTextColor(115, 115, 115);
                $pdf->SetFont('Arial', '', 7);
                $pdf->SetXY(8, max(4, $size['height'] - 14));
                $watermark = 'Solopos Membership: '.Str::ascii($download->user->email)
                    ."\nDownload ID: ".$download->watermark_hash;
                $pdf->MultiCell($size['width'] - 16, 4, $watermark, 0, 'C');
            }

            $pdf->Output('F', $output);
            $this->storeGeneratedFile($disk, $output, $download->generated_pdf_path);
            $download->forceFill([
                'status' => EpaperDownloadStatus::Ready,
                'expires_at' => now()->addMinutes((int) config('epaper.signed_url_ttl_minutes')),
            ])->save();
        } finally {
            unlink($source);
            if (file_exists($output)) {
                unlink($output);
            }
        }
    }

    public function cleanupExpired(): int
    {
        $disk = $this->storage->disk();
        $cleaned = 0;

        EpaperDownload::query()
            ->whereNotNull('generated_pdf_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($downloads) use ($disk, &$cleaned): void {
                foreach ($downloads as $download) {
                    if ($disk->exists($download->generated_pdf_path) && ! $disk->delete($download->generated_pdf_path)) {
                        throw new RuntimeException('Unable to delete an expired E-Paper download file.');
                    }

                    $download->forceFill([
                        'status' => EpaperDownloadStatus::Expired,
                        'generated_pdf_path' => null,
                    ])->save();
                    $cleaned++;
                }
            });

        return $cleaned;
    }

    public function usedQuota(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $this->quotaQuery($user, $start, $end)->count();
    }

    /**
     * @return Builder<EpaperDownload>
     */
    private function quotaQuery(User $user, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return EpaperDownload::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($reservations) use ($start, $end): void {
                    $reservations->whereNull('downloaded_at')
                        ->where('requested_at', '>=', $start)
                        ->where('requested_at', '<', $end)
                        ->where(function ($statuses): void {
                            $statuses->where('status', EpaperDownloadStatus::Processing)
                                ->orWhere(function ($ready): void {
                                    $ready->where('status', EpaperDownloadStatus::Ready)
                                        ->where('expires_at', '>', now());
                                });
                        });
                })->orWhere(function ($completed) use ($start, $end): void {
                    $completed->whereNotNull('downloaded_at')
                        ->where('downloaded_at', '>=', $start)
                        ->where('downloaded_at', '<', $end);
                });
            });
    }

    private function storeGeneratedFile(FilesystemAdapter $disk, string $sourcePath, ?string $destinationPath): void
    {
        if ($destinationPath === null) {
            throw new RuntimeException('The E-Paper download does not have a generated file path.');
        }

        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read the generated E-Paper PDF.');
        }

        try {
            if (! $disk->writeStream($destinationPath, $stream)) {
                throw new RuntimeException('Unable to store the generated E-Paper PDF.');
            }
        } finally {
            fclose($stream);
        }
    }
}
