<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeRole
{
    public function handle(Request $request, Closure $next, string $minimumRole = 'staff'): Response
    {
        $user = $request->user();
        $role = UserRole::tryFrom($minimumRole);
        $hasAccess = match ($role) {
            UserRole::Admin => $user?->isBackofficeAdmin(),
            UserRole::Staff => $user?->isBackofficeStaff(),
            default => false,
        };

        if (! $user || ! $user->hasVerifiedEmail() || ! $hasAccess) {
            return response()->json([
                'error' => [
                    'code' => 'backoffice_access_required',
                    'message' => 'Akses backoffice tidak diizinkan.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
