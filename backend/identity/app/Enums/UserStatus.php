<?php

namespace App\Enums;

enum UserStatus: string
{
    case Ativo = 'ativo';
    case Bloqueado = 'bloqueado';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
