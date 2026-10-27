<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\NotificationDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendSubscriptionNotifications extends Command
{
    protected $signature = 'subscriptions:send-notifications';

    protected $description = 'Queue subscription expiry reminders for members.';

    public function handle(NotificationDeliveryService $deliveries): int
    {
        $now = CarbonImmutable::now('Asia/Jakarta');

        foreach ([7, 3, 1] as $days) {
            $localStart = $now->addDays($days)->startOfDay();
            $start = $localStart->setTimezone(config('app.timezone'));
            $end = $localStart->endOfDay()->setTimezone(config('app.timezone'));

            Subscription::query()
                ->with(['user', 'plan'])
                ->where('status', SubscriptionStatus::Active)
                ->whereBetween('end_date', [$start, $end])
                ->chunkById(100, function ($subscriptions) use ($deliveries, $days): void {
                    foreach ($subscriptions as $subscription) {
                        $hasRenewal = $subscription->user->subscriptions()
                            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])
                            ->where('end_date', '>', $subscription->end_date)
                            ->exists();
                        if ($hasRenewal) {
                            continue;
                        }

                        $deliveries->queueSubscriptionNotice($subscription, 'subscription.reminder.h'.$days);
                    }
                });
        }

        return self::SUCCESS;
    }
}
