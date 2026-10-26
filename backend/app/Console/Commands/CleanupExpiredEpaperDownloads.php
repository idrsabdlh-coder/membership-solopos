<?php

namespace App\Console\Commands;

use App\Services\EpaperDownloadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cleanup-expired-epaper-downloads')]
#[Description('Remove expired generated E-Paper PDFs while preserving their audit records')]
class CleanupExpiredEpaperDownloads extends Command
{
    public function handle(EpaperDownloadService $service): int
    {
        $cleaned = $service->cleanupExpired();
        $this->info("Cleaned up {$cleaned} expired E-Paper download file(s).");

        return self::SUCCESS;
    }
}
