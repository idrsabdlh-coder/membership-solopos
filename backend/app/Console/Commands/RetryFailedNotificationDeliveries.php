<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationDelivery;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;

class RetryFailedNotificationDeliveries extends Command
{
    protected $signature = 'notifications:retry-failed';

    protected $description = 'Queue failed transactional emails for another delivery attempt.';

    public function handle(): int
    {
        $retried = 0;

        NotificationDelivery::query()
            ->where('status', 'failed')
            ->orderBy('id')
            ->chunkById(100, function ($deliveries) use (&$retried): void {
                foreach ($deliveries as $delivery) {
                    $updated = NotificationDelivery::query()
                        ->whereKey($delivery->id)
                        ->where('status', 'failed')
                        ->update([
                            'status' => 'queued',
                            'failed_at' => null,
                            'failure_reason' => null,
                            'updated_at' => now(),
                        ]);

                    if ($updated === 1) {
                        SendNotificationDelivery::dispatch($delivery->id)->afterCommit();
                        $retried++;
                    }
                }
            });

        $this->info("Queued {$retried} failed notification(s) for retry.");

        return self::SUCCESS;
    }
}
