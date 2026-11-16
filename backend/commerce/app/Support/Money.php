<?php

namespace App\Support;

final class Money
{
    /** 3000 -> "R$ 30,00" */
    public static function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
