<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'redirect_url' => $this->redirect_url,
            'paid_at' => $this->paid_at?->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
