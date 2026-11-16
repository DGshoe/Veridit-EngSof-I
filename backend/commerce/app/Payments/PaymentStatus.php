<?php

namespace App\Payments;

/** Provider status normalized by each gateway (anti-corruption layer, ADR-006). */
enum PaymentStatus: string
{
    case Approved = 'aprovado';
    case Rejected = 'recusado';
    case Pending = 'pendente';
}
