<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['question', 'answer', 'category', 'order'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['order' => 'integer'];
    }
}
