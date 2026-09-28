<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /**
     * Mirrors the column defaults so freshly created models serialize them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_USER,
        'plan' => 'free',
        'auth_method' => 'email',
    ];

    /**
     * `role`, `plan` and `email_verified_at` are fillable so the admin panel can edit them. API controllers must
     * never pass raw request input to fill()/create() for this model.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'plan',
        'email_verified_at',
        'auth_method',
        'github_id',
        'github_login',
        'avatar_url',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'github_id' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Label of the user's plan, e.g. "Free". */
    public function planLabel(): string
    {
        return config("atglance.plans.{$this->plan}.label", ucfirst((string) $this->plan));
    }

    /** Max Management Console licences for the user's plan; null = unlimited. Unknown plans get the free limit. */
    public function licenseLimit(): ?int
    {
        $plans = config('atglance.plans');

        return array_key_exists($this->plan, $plans)
            ? $plans[$this->plan]['licenses']
            : $plans[config('atglance.default_plan')]['licenses'];
    }

    /** GitHub users without a public/verified email get an unreachable noreply address. */
    public function hasDeliverableEmail(): bool
    {
        return ! Str::endsWith($this->email, '@users.noreply.github.com');
    }

    /**
     * Send the verification link, but never fail the caller (signup) if mail is misconfigured.
     */
    public function sendVerificationSafely(): bool
    {
        if ($this->hasVerifiedEmail() || ! $this->hasDeliverableEmail()) {
            return false;
        }

        try {
            $this->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            Log::error('Could not send verification email', ['user' => $this->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
