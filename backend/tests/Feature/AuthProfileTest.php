<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_registration_creates_an_unverified_user_and_sends_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Pembaca Solopos',
            'email' => 'READER@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'whatsapp' => '+628123456789',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'reader@example.com')
            ->assertJsonPath('data.email_verified_at', null)
            ->assertJsonPath('data.whatsapp', '+628123456789')
            ->assertJsonPath('meta.email_verification', 'pending')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'reader@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validates_unique_email_password_confirmation_and_whatsapp(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Reader',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
            'whatsapp' => '08123456789',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'whatsapp']);
    }

    public function test_registration_rejects_email_duplicates_without_case_sensitivity(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Reader',
            'email' => 'TAKEN@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_only_signed_email_verification_links_are_accepted(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson("/api/auth/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Email berhasil diverifikasi.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_links_expire(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->getJson($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_requires_correct_credentials_and_a_verified_email(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'secret1234',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_users_cannot_log_in(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'secret1234',
            ])
            ->assertForbidden();

        $this->assertGuest('web');
        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/auth/profile')
            ->assertUnauthorized();
    }

    public function test_password_reset_responses_do_not_disclose_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reader@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, instruksi reset kata sandi akan dikirim.');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, instruksi reset kata sandi akan dikirim.');

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, instruksi reset kata sandi akan dikirim.');
    }

    public function test_password_reset_token_is_single_use(): void
    {
        $user = User::factory()->create(['email' => 'reader@example.com']);
        $token = Password::createToken($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newsecret1234',
            'password_confirmation' => 'newsecret1234',
        ];

        $this->postJson('/api/auth/reset-password', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diatur ulang.');

        $this->assertTrue(password_verify('newsecret1234', $user->fresh()->password));
        $this->postJson('/api/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_profile_is_protected_and_can_update_profile_fields_and_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->getJson('/api/auth/profile')->assertUnauthorized();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', [
                'name' => 'Nama Baru',
                'whatsapp' => '+628123456789',
                'avatar' => UploadedFile::fake()->image('avatar.png'),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.whatsapp', '+628123456789')
            ->assertJsonPath('data.avatar_url', fn (string $url): bool => str_contains($url, 'avatars/'));

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_profile_timestamps_are_returned_in_wib(): void
    {
        $user = User::factory()->create(['email_verified_at' => '2026-10-19 00:00:00']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.email_verified_at', '2026-10-19T07:00:00+07:00');
    }

    public function test_profile_does_not_allow_email_changes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['email' => 'other@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_password_can_be_changed_after_verifying_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/password', [
                'current_password' => 'wrongpassword',
                'password' => 'newsecret1234',
                'password_confirmation' => 'newsecret1234',
            ])
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/password', [
                'current_password' => 'secret1234',
                'password' => 'newsecret1234',
                'password_confirmation' => 'newsecret1234',
            ])
            ->assertOk();

        $this->assertTrue(password_verify('newsecret1234', $user->fresh()->password));
    }

    public function test_logout_clears_the_authenticated_session(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'secret1234',
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->assertGuest('web');
        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/auth/profile')
            ->assertUnauthorized();
    }

    public function test_login_rejects_requests_without_a_stateful_origin(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ])->assertBadRequest();
    }

    public function test_login_is_rate_limited_by_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('Origin', 'http://localhost:3000')
                ->postJson('/api/auth/login', [
                    'email' => 'reader@example.com',
                    'password' => 'wrongpassword',
                ])
                ->assertUnprocessable();
        }

        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => 'reader@example.com',
                'password' => 'wrongpassword',
            ])
            ->assertTooManyRequests();
    }

    public function test_cors_credentials_and_csrf_cookie_are_available_to_local_spa(): void
    {
        $this->options('/api/auth/login', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }
}
