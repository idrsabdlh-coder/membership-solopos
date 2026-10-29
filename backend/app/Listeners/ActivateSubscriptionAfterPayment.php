<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Subscription;
use App\Services\AnalyticsEventService;
use App\Services\NotificationDeliveryService;
use App\Services\SubscriptionLifecycleService;

class ActivateSubscriptionAfterPayment
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptions,
        private readonly NotificationDeliveryService $deliveries,
        private readonly AnalyticsEventService $analytics,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $subscription = $this->subscriptions->activateFromPayment($event->order, $event->payment);
        $hasPriorSubscription = Subscription::query()
            ->where('user_id', $event->order->user_id)
            ->where('id', '!=', $subscription->id)
            ->where('start_date', '<=', $subscription->start_date)
            ->exists();
        if ($event->order->upgrade_from_subscription_id === null && $hasPriorSubscription) {
            $this->analytics->recordGenerated('renewal_success', 'payment', $event->payment->id, [
                'plan_id' => $event->order->plan_id,
            ]);
        }

        $event->order->loadMissing(['user', 'plan']);
        $this->deliveries->queuePaymentOutcome($event->order, $event->payment);
    }
}
