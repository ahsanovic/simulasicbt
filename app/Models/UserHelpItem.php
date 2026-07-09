<?php

namespace App\Models;

use App\Enums\HelpItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHelpItem extends Model
{
    protected $fillable = [
        'user_id',
        'item',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpItem(): HelpItem
    {
        return HelpItem::from($this->item);
    }
}
