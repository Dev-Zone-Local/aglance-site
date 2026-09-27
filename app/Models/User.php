<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
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
     * `role` and `plan` are fillable so the admin panel can edit them. API controllers must
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

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
