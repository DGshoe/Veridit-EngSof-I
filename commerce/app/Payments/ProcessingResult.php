<?php

namespace App\Payments;

enum ProcessingResult: string
{
    case Applied = 'aplicado';     // changed the order (and the wallet, when approved)
    case Ignored = 'ignorado';     // valid event without effect (unknown payment, order already final, pending)
    case Duplicate = 'duplicado';  // same event already received: nothing done
}
