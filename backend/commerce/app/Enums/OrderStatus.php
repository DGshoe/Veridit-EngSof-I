<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pendente';
    case Paid = 'pago';
    case Failed = 'falhou';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
