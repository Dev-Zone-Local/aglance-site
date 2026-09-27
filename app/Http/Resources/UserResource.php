<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'plan' => $this->plan,
            'avatar_url' => $this->avatar_url,
            'github_login' => $this->github_login,
            'auth_method' => $this->auth_method,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
