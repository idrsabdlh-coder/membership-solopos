<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\NotificationDeliveryService;
use App\Services\SubscriptionLifecycleService;

class ActivateSubscriptionAfterPayment
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptions,
        private readonly NotificationDeliveryService $deliveries,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->subscriptions->activateFromPayment($event->order, $event->payment);
        $event->order->loadMissing(['user', 'plan']);
        $this->deliveries->queuePaymentOutcome($event->order, $event->payment);
    }
}
