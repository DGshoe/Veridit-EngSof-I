<?php

namespace App\Services;

use App\Jobs\SendPasswordResetLink;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * REQ02 — password recovery and reset.
 */
class PasswordResetService
{
    /** Never reveals whether the e-mail exists: the lookup happens in the queue. */
    public function requestLink(string $email): void
    {
        SendPasswordResetLink::dispatch(mb_strtolower(trim($email)));
    }

    /**
     * Sets the new password, consumes the reset token and, by changing the
     * password fingerprint ("pwv" claim), invalidates every previous JWT.
     *
     * @throws ValidationException
     */
    public function reset(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => mb_strtolower(trim($email)), 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Same message for unknown e-mail and bad/expired token.
            throw ValidationException::withMessages(['token' => [__('passwords.token')]]);
        }
    }
}
