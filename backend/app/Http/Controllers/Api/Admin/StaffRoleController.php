<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCustomerResource;
use App\Models\User;
use App\Services\BackofficeAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffRoleController extends Controller
{
    public function update(Request $request, User $user, BackofficeAuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in([UserRole::Member->value, UserRole::Staff->value])],
        ]);

        $configuredStaff = in_array(
            Str::lower($user->email),
            config('backoffice.staff_emails', []),
            true
        );

        if ($user->isBackofficeAdmin() || $configuredStaff) {
            return response()->json([
                'error' => [
                    'code' => 'configured_role_locked',
                    'message' => 'Role dari konfigurasi server tidak dapat diubah melalui API.',
                ],
            ], 409);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => [
                    'code' => 'staff_email_not_verified',
                    'message' => 'Pengguna harus memverifikasi email sebelum diberi akses staf.',
                ],
            ], 409);
        }

        /** @var User $actor */
        $actor = $request->user();
        $result = DB::transaction(function () use ($user, $validated, $actor, $request, $audit): User|JsonResponse {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($lockedUser->isBackofficeAdmin()
                || in_array(Str::lower($lockedUser->email), config('backoffice.staff_emails', []), true)) {
                return response()->json([
                    'error' => [
                        'code' => 'configured_role_locked',
                        'message' => 'Role dari konfigurasi server tidak dapat diubah melalui API.',
                    ],
                ], 409);
            }
            if (! $lockedUser->hasVerifiedEmail()) {
                return response()->json([
                    'error' => [
                        'code' => 'staff_email_not_verified',
                        'message' => 'Pengguna harus memverifikasi email sebelum diberi akses staf.',
                    ],
                ], 409);
            }

            $before = $lockedUser->role->value;
            $lockedUser->role = UserRole::from($validated['role']);
            $lockedUser->save();
            $lockedUser->loadCount('subscriptions');

            $audit->record($actor, $request, 'user.role_updated', 'user', $lockedUser->id, [
                'before' => ['role' => $before],
                'after' => ['role' => $lockedUser->role->value],
            ]);

            return $lockedUser;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'data' => AdminCustomerResource::make($result)->resolve($request),
        ]);
    }
}
