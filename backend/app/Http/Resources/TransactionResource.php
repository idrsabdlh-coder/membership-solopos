<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->relationLoaded('payments') ? $this->payments->first() : null;
        $subscription = $this->relationLoaded('subscription') ? $this->subscription : null;

        return [
            'order_number' => $this->order_number,
            'type' => $this->upgrade_from_subscription_id !== null ? 'upgrade' : 'subscription',
            'plan' => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'type' => $this->plan->type->value,
                'billing_period' => $this->plan->billing_period->value,
            ],
            'period' => $subscription ? [
                'start_date' => $subscription->start_date->timezone('Asia/Jakarta')->toIso8601String(),
                'end_date' => $subscription->end_date->timezone('Asia/Jakarta')->toIso8601String(),
                'status' => $subscription->status->value,
            ] : null,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'upgrade_credit' => $this->upgrade_credit,
            'total' => $this->total,
            'order_status' => $this->status->value,
            'payment_status' => $payment?->status->value,
            'payment' => $payment ? PaymentResource::make($payment) : null,
            'invoice_url' => route('api.orders.invoice', $this->id),
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
