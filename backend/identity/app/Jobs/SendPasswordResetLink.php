<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

/**
 * REQ02 — runs in the queue so that the HTTP response of forgot-password
 * takes the same time whether the e-mail is registered or not.
 */
class SendPasswordResetLink implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $email) {}

    public function handle(): void
    {
        // Unknown e-mails and throttled re-sends are silently ignored on purpose.
        Password::broker()->sendResetLink(['email' => $this->email]);
    }
}
