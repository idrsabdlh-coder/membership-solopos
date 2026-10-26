<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpaperPublicEditionResource extends JsonResource
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
            'edition_number' => $this->edition_number,
            'edition_year' => $this->edition_year,
            'publish_date' => $this->publish_date->toDateString(),
            'publish_end_date' => $this->publish_end_date->toDateString(),
            'page_count' => $this->page_count,
            'has_cover' => $this->cover_path !== null,
            'cover_url' => $this->cover_path === null
                ? null
                : route('api.epaper.editions.cover', ['edition' => $this->id]),
            'access' => ($this->getAttribute('access_allowed') ?? false) ? 'unlocked' : 'locked',
        ];
    }
}
