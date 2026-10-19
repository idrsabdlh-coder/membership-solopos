<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+62[0-9]{8,13}$/'],
        ]);

        $user = User::create([
            ...$validated,
            'email' => Str::lower($validated['email']),
        ]);

        $user->sendEmailVerificationNotification();

        return UserResource::make($user)
            ->additional(['meta' => ['email_verification' => 'pending']])
            ->response()
            ->setStatusCode(201);
    }

    public function login(Request $request): UserResource|JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'Login harus dilakukan dari domain aplikasi yang terdaftar untuk autentikasi sesi.',
            ], 400);
        }

        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak sesuai.'],
            ]);
        }

        $user = Auth::guard('web')->user();

        if (! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => 'Verifikasi email terlebih dahulu sebelum masuk.',
            ], 403);
        }

        $request->session()->regenerate();

        return UserResource::make($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token && ! $token instanceof TransientToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([], 204);
    }

    public function verifyEmail(Request $request, int $id, string $hash): UserResource|JsonResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email berhasil diverifikasi.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()->where('email', Str::lower($validated['email']))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Jika akun memerlukan verifikasi, instruksi akan dikirim ke email tersebut.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink([
            'email' => Str::lower($validated['email']),
        ]);

        return response()->json([
            'message' => 'Jika email terdaftar, instruksi reset kata sandi akan dikirim.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Kata sandi berhasil diatur ulang.']);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
