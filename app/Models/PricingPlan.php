<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PricingPlan extends Model
{
    protected $fillable = [
        'name', 'price', 'period', 'description', 'features', 'cta', 'highlighted', 'order',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'highlighted' => 'boolean',
            'order' => 'integer',
        ];
    }

    /** Plan code users carry in users.plan, e.g. "Enterprise" → "enterprise". */
    public function code(): string
    {
        return Str::slug($this->name, '_');
    }
}
