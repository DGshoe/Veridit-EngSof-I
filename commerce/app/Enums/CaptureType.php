<?php

namespace App\Enums;

/** Capture types sold in credits (team decision: image 1, video 2). */
enum CaptureType: string
{
    case Image = 'imagem';
    case Video = 'video';

    public function cost(): int
    {
        return (int) config('commerce.capture_costs.'.$this->value);
    }

    /** Ledger reason for the debit. */
    public function reason(): string
    {
        return 'captura_'.$this->value;
    }
}
