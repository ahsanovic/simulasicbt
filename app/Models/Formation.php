<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'group',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
