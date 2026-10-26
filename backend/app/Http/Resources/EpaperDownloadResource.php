<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class EpaperDownloadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $isAvailable = in_array($status?->value, ['ready', 'downloaded'], true)
            && $this->expires_at?->isFuture();
        $reportedStatus = $isAvailable ? $status->value : (
            in_array($status?->value, ['ready', 'downloaded'], true) ? 'expired' : $status?->value
        );

        return [
            'id' => $this->id,
            'edition' => [
                'id' => $this->edition->id,
                'edition_number' => $this->edition->edition_number,
                'edition_year' => $this->edition->edition_year,
            ],
            'status' => $reportedStatus,
            'file_name' => $this->file_name,
            'requested_at' => $this->requested_at?->timezone('Asia/Jakarta')->toIso8601String(),
            'downloaded_at' => $this->downloaded_at?->timezone('Asia/Jakarta')->toIso8601String(),
            'expires_at' => $this->expires_at?->timezone('Asia/Jakarta')->toIso8601String(),
            'download_url' => $isAvailable
                ? URL::temporarySignedRoute(
                    'api.epaper.downloads.file',
                    $this->expires_at,
                    ['download' => $this->id],
                )
                : null,
            ...($status?->value === 'processing' ? ['retry_after_seconds' => 2] : []),
        ];
    }
}
