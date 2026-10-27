<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->timezone('Asia/Jakarta')->toIso8601String(),
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status->value,
                'customer' => [
                    'id' => $this->order->user->id,
                    'name' => $this->order->user->name,
                    'email' => $this->order->user->email,
                ],
            ],
        ];
    }
}
