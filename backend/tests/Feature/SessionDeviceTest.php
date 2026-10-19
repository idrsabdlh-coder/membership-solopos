<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_third_login_revokes_the_least_recently_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);
        $oldest = $this->createSession($user, 'oldest-session', now()->subMinutes(3)->timestamp);
        $newer = $this->createSession($user, 'newer-session', now()->subMinute()->timestamp);

        $response = $this->login($user, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0');
        $response->assertOk()->assertJsonPath('meta.previous_session_revoked', true);

        $this->assertDatabaseMissing('sessions', ['id' => $oldest]);
        $this->assertDatabaseHas('sessions', ['id' => $newer, 'user_id' => $user->id]);
        $this->assertSame(
            2,
            DB::table('sessions')->where('user_id', $user->id)->count()
        );

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.device', 'Chrome di Windows')
            ->assertJsonPath('meta.max_active_sessions', 2);
    }

    public function test_login_does_not_revoke_a_session_when_below_limit(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);
        $existing = $this->createSession($user, 'existing-session', now()->subMinute()->timestamp);

        $this->login($user)
            ->assertOk()
            ->assertJsonPath('meta.previous_session_revoked', false);

        $this->assertDatabaseHas('sessions', ['id' => $existing]);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_a_user_can_revoke_another_owned_session_but_not_the_current_session(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);
        $otherSession = $this->createSession($user, 'other-device-session', now()->subMinute()->timestamp);

        $this->login($user)->assertOk();

        $sessions = $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/auth/sessions')
            ->assertOk()
            ->json('data');
        $currentSession = collect($sessions)->firstWhere('is_current', true);

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->deleteJson('/api/auth/sessions/'.$currentSession['id'])
            ->assertUnprocessable();

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->deleteJson('/api/auth/sessions/'.hash('sha256', $otherSession))
            ->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => $otherSession]);
        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/auth/profile')
            ->assertOk();
    }

    public function test_a_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);
        $otherUser = User::factory()->create();
        $otherSession = $this->createSession($otherUser, 'not-owned-session', now()->subMinute()->timestamp);

        $this->login($user)->assertOk();

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:3000')
            ->deleteJson('/api/auth/sessions/'.hash('sha256', $otherSession))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $otherUser->id]);
    }

    public function test_session_management_requires_the_shared_database_session_store(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret1234',
        ]);

        config(['session.driver' => 'array']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/sessions')
            ->assertServiceUnavailable();
    }

    private function createSession(User $user, string $id, int $lastActivity): string
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0',
            'payload' => base64_encode(json_encode([])),
            'last_activity' => $lastActivity,
        ]);

        return $id;
    }

    private function login(User $user, ?string $userAgent = null): TestResponse
    {
        $request = $this->withHeader('Origin', 'http://localhost:3000');
        if ($userAgent) {
            $request->withHeader('User-Agent', $userAgent);
        }

        $response = $request->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ]);
        $cookie = $response->getCookie(config('session.cookie'));

        if ($cookie) {
            $this->withCredentials()->withCookie(config('session.cookie'), $cookie->getValue());
        }

        return $response;
    }
}
