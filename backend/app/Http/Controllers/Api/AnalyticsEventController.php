<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsEventController extends Controller
{
    public function store(Request $request, AnalyticsEventService $events): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'name' => [
                'required',
                Rule::in([
                    'view_pricing',
                    'select_plan',
                    'begin_checkout',
                    'epaper_open',
                    'quota_reached',
                    'upgrade_click',
                ]),
            ],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'feature' => ['sometimes', Rule::in(['clipping', 'epaper_download', 'epaper_archive'])],
            'reason' => ['sometimes', Rule::in(['quota_full', 'archive_locked'])],
            'surface' => ['sometimes', Rule::in(['pricing', 'checkout', 'dashboard', 'epaper', 'clipping'])],
        ]);

        $properties = array_intersect_key($validated, array_flip(['plan_id', 'feature', 'reason', 'surface']));
        $events->record($validated['name'], 'client:'.$validated['event_id'], $properties);

        return response()->json(['accepted' => true], 202)
            ->header('Cache-Control', 'no-store');
    }
}
