<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyticsEventService
{
    /**
     * @param  array<string, int|string>  $properties
     */
    public function record(string $name, string $eventKey, array $properties = []): void
    {
        $now = now();
        DB::table('analytics_events')->insertOrIgnore([
            'event_key' => Str::limit($eventKey, 191, ''),
            'name' => $name,
            'properties' => $properties === [] ? null : json_encode($properties, JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, int|string>  $properties
     */
    public function recordGenerated(string $name, string $sourceType, int|string $sourceId, array $properties = []): void
    {
        $this->record($name, $name.':'.$sourceType.':'.$sourceId, $properties);
    }
}
