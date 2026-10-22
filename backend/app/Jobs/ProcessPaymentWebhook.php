<?php

namespace App\Jobs;

use App\Models\PaymentWebhookEvent;
use App\Services\PaymentStatusProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $eventId) {}

    public function backoff(): array
    {
        return [10, 30, 60, 300, 600];
    }

    public function handle(PaymentStatusProcessor $processor): void
    {
        $processor->processWebhookEvent($this->eventId);
    }

    public function failed(Throwable $exception): void
    {
        PaymentWebhookEvent::query()
            ->whereKey($this->eventId)
            ->update([
                'failed_at' => now(),
                'failure_reason' => 'Webhook processing failed after retries.',
                'updated_at' => now(),
            ]);

        Log::error('Payment webhook processing failed after retries.', [
            'payment_webhook_event_id' => $this->eventId,
            'exception' => $exception::class,
        ]);
    }
}
