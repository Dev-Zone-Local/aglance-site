<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doc extends Model
{
    protected $fillable = ['slug', 'title', 'section', 'content', 'order'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['order' => 'integer'];
    }
}
