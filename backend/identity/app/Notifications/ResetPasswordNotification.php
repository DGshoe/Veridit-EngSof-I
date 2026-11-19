<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * REQ02 — e-mail with the link to the front-end password reset page.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Redefinição de senha — Veridit')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta no Veridit.')
            ->action('Redefinir senha', $this->resetUrl($notifiable))
            ->line("Este link expira em {$minutes} minutos.")
            ->line('Se você não fez essa solicitação, ignore este e-mail: sua senha continua a mesma.')
            ->salutation('Equipe Veridit');
    }

    /** FRONTEND_URL/redefinir-senha?token=...&email=... */
    public function resetUrl(User $notifiable): string
    {
        return rtrim((string) config('identity.frontend_url'), '/').'/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }
}
