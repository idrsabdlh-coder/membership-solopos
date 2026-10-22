<?php

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class TransitionSubscriptions extends Command
{
    protected $signature = 'subscriptions:transition';

    protected $description = 'Activate scheduled subscriptions and expire ended subscriptions';

    public function handle(SubscriptionLifecycleService $subscriptions): int
    {
        $transitioned = $subscriptions->transitionStatuses();
        $this->info("Transitioned {$transitioned} subscription(s).");

        return self::SUCCESS;
    }
}
