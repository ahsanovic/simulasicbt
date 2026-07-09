<?php

namespace App\Models;

use App\Enums\HelpItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTransaction extends Model
{
    public const SOURCE_HELP_ITEM_PURCHASE = 'help_item_purchase';

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'source_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query
            ->where('source_type', self::SOURCE_HELP_ITEM_PURCHASE)
            ->where('amount', '<', 0);
    }

    public function itemLabel(): string
    {
        return str_starts_with($this->reason, 'Beli ')
            ? substr($this->reason, 5)
            : $this->reason;
    }

    public function pricePaid(): int
    {
        return abs($this->amount);
    }

    public function helpItem(): ?HelpItem
    {
        foreach (HelpItem::cases() as $item) {
            if ($item->label() === $this->itemLabel()) {
                return $item;
            }
        }

        return null;
    }
}
