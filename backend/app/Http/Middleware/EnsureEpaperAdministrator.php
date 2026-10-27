<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEpaperAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $email = strtolower((string) $user?->email);
        $adminEmails = config('epaper.admin_emails', []);

        if (! $user || ! $user->hasVerifiedEmail()
            || (! $user->isBackofficeAdmin() && ! in_array($email, $adminEmails, true))) {
            return response()->json([
                'error' => [
                    'code' => 'epaper_admin_required',
                    'message' => 'Akses pengelolaan E-Paper tidak diizinkan.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
