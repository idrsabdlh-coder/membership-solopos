<?php

namespace App\Jobs;

use App\Enums\EpaperDownloadStatus;
use App\Models\EpaperDownload;
use App\Services\EpaperDownloadService;
use App\Services\EpaperPrivateStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class GenerateEpaperDownload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public readonly int $downloadId) {}

    public function handle(EpaperDownloadService $service): void
    {
        $download = EpaperDownload::query()->findOrFail($this->downloadId);
        if ($download->status !== EpaperDownloadStatus::Processing) {
            return;
        }

        $service->generate($download);
    }

    public function failed(?Throwable $exception): void
    {
        $download = EpaperDownload::query()->find($this->downloadId);
        if ($download === null || $download->status !== EpaperDownloadStatus::Processing) {
            return;
        }

        $disk = app(EpaperPrivateStorage::class)->disk();
        if ($download->generated_pdf_path !== null
            && $disk->exists($download->generated_pdf_path)
            && ! $disk->delete($download->generated_pdf_path)) {
            report(new RuntimeException('Unable to delete a failed E-Paper download file.', previous: $exception));
        }

        $download->forceFill([
            'status' => EpaperDownloadStatus::Failed,
            'expires_at' => now(),
        ])->save();
    }
}
