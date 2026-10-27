<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminVoucherResource extends JsonResource
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
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'quota' => $this->quota,
            'used' => $this->used,
            'valid_from' => $this->valid_from?->timezone('Asia/Jakarta')->toIso8601String(),
            'valid_until' => $this->valid_until?->timezone('Asia/Jakarta')->toIso8601String(),
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
