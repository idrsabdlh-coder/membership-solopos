<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpaperEditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'edition_number' => $this->edition_number,
            'edition_year' => $this->edition_year,
            'publish_date' => $this->publish_date->toDateString(),
            'publish_end_date' => $this->publish_end_date->toDateString(),
            'checksum' => $this->checksum,
            'file_size' => $this->file_size,
            'page_count' => $this->page_count,
            'status' => $this->status->value,
            'has_cover' => $this->cover_path !== null,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->toIso8601String(),
            'updated_at' => $this->updated_at->timezone('Asia/Jakarta')->toIso8601String(),
        ];
    }
}
