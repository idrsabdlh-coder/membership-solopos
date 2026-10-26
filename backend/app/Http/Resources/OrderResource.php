<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'plan' => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'type' => $this->plan->type->value,
                'billing_period' => $this->plan->billing_period->value,
                'duration_days' => $this->plan->duration_days,
            ],
            'voucher' => $this->voucher ? [
                'code' => $this->voucher->code,
                'type' => $this->voucher->type,
                'value' => $this->voucher->value,
            ] : null,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'upgrade_credit' => $this->upgrade_credit,
            'total' => $this->total,
            'type' => $this->upgrade_from_subscription_id !== null ? 'upgrade' : 'subscription',
            'status' => $this->status->value,
            'payment' => $this->relationLoaded('payments') && $this->payments->isNotEmpty()
                ? PaymentResource::make($this->payments->first())
                : null,
            'invoice_url' => route('api.orders.invoice', $this->id),
            'expires_at' => $this->expires_at->timezone('Asia/Jakarta')->toIso8601String(),
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
