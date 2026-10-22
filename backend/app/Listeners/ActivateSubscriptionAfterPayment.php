<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\SubscriptionLifecycleService;

class ActivateSubscriptionAfterPayment
{
    public function __construct(private readonly SubscriptionLifecycleService $subscriptions) {}

    public function handle(OrderPaid $event): void
    {
        $this->subscriptions->activateFromPayment($event->order, $event->payment);
    }
}
