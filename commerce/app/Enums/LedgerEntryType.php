<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Credit = 'credito';
    case Debit = 'debito';
}
