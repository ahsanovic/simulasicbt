<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Peserta = 'peserta';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Peserta => 'Peserta',
        };
    }
}
