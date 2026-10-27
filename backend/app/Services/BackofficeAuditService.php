<?php

namespace App\Services;

use App\Models\BackofficeAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class BackofficeAuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        Request $request,
        string $action,
        string $subjectType,
        ?int $subjectId = null,
        array $metadata = [],
    ): BackofficeAuditLog {
        return BackofficeAuditLog::query()->create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
