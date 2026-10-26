<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('sourceOrder') ? $this->sourceOrder : null;
        $payment = $order && $order->relationLoaded('payments')
            ? $order->payments->first()
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'start_date' => $this->start_date->timezone('Asia/Jakarta')->toIso8601String(),
            'end_date' => $this->end_date->timezone('Asia/Jakarta')->toIso8601String(),
            'plan' => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'type' => $this->plan->type->value,
                'billing_period' => $this->plan->billing_period->value,
                'price' => $order?->subtotal ?? $this->plan->price,
            ],
            'transaction' => $order ? [
                'order_number' => $order->order_number,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'upgrade_credit' => $order->upgrade_credit,
                'total' => $order->total,
                'order_status' => $order->status->value,
                'payment_status' => $payment?->status->value,
                'invoice_url' => route('api.orders.invoice', $order->id),
            ] : null,
        ];
    }
}
