<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'whatsapp', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function backofficeRole(): UserRole
    {
        $email = Str::lower($this->email);

        if ($this->isConfiguredBackofficeAdmin()) {
            return UserRole::Admin;
        }

        if (in_array($email, config('backoffice.staff_emails', []), true)) {
            return UserRole::Staff;
        }

        return $this->role ?? UserRole::Member;
    }

    public function isConfiguredBackofficeAdmin(): bool
    {
        return in_array(
            Str::lower($this->email),
            config('backoffice.admin_emails', []),
            true
        );
    }

    public function isBackofficeAdmin(): bool
    {
        return $this->backofficeRole() === UserRole::Admin;
    }

    public function isBackofficeStaff(): bool
    {
        return in_array($this->backofficeRole(), [UserRole::Admin, UserRole::Staff], true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function backofficeAuditLogs(): HasMany
    {
        return $this->hasMany(BackofficeAuditLog::class, 'actor_id');
    }
}
