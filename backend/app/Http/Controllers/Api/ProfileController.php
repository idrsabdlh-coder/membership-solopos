<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as PasswordRule;
use RuntimeException;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['prohibited'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:/^\+62[0-9]{8,13}$/'],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $user->fill(array_intersect_key($validated, array_flip(['name', 'whatsapp'])));

        if ($request->hasFile('avatar')) {
            $oldAvatarPath = $user->avatar_path;
            $newAvatarPath = $request->file('avatar')->store('avatars', 'public');

            if ($newAvatarPath === false) {
                throw new RuntimeException('Avatar could not be stored.');
            }

            $user->avatar_path = $newAvatarPath;
            $user->save();

            if ($oldAvatarPath) {
                Storage::disk('public')->delete($oldAvatarPath);
            }

            return UserResource::make($user->refresh());
        }

        $user->save();

        return UserResource::make($user->refresh());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json([
                'message' => 'Kata sandi saat ini tidak sesuai.',
                'errors' => ['current_password' => ['Kata sandi saat ini tidak sesuai.']],
            ], 422);
        }

        $request->user()->forceFill(['password' => $validated['password']])->save();

        return response()->json(['message' => 'Kata sandi berhasil diubah.']);
    }
}
