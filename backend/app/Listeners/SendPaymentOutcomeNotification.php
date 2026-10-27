<?php

namespace App\Listeners;

use App\Events\OrderPaymentStatusChanged;
use App\Services\NotificationDeliveryService;

class SendPaymentOutcomeNotification
{
    public function __construct(private readonly NotificationDeliveryService $deliveries) {}

    public function handle(OrderPaymentStatusChanged $event): void
    {
        $this->deliveries->queuePaymentOutcome($event->order, $event->payment);
    }
}
