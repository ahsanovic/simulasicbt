<?php

namespace App\Enums;

enum EventExamMode: string
{
    case Skd = 'skd';
    case Skb = 'skb';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Skd => 'SKD',
            self::Skb => 'SKB',
            self::Both => 'SKD & SKB',
        };
    }

    public function includesSkd(): bool
    {
        return $this === self::Skd || $this === self::Both;
    }

    public function includesSkb(): bool
    {
        return $this === self::Skb || $this === self::Both;
    }
}
