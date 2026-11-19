<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['identity.frontend_url' => 'https://front.veridit.test']);
    }

    // ---- forgot-password -----------------------------------------------

    public function test_forgot_password_sends_email_with_front_end_link_for_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'maria@exemplo.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'Maria@Exemplo.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::SENT]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $url = $notification->resetUrl($user);
            $mail = $notification->toMail($user);

            return str_starts_with($url, 'https://front.veridit.test/redefinir-senha?token=')
                && str_contains($url, 'email=maria%40exemplo.com')
                && $mail->actionUrl === $url
                && Password::broker()->tokenExists($user, $notification->token);
        });
    }

    public function test_forgot_password_gives_same_answer_for_unknown_email_and_sends_nothing(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ninguem@exemplo.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::SENT]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_is_limited_to_3_per_minute(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => "x{$i}@exemplo.com"])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'x4@exemplo.com'])->assertTooManyRequests();
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ---- reset-password ------------------------------------------------

    public function test_reset_with_valid_token_changes_password_and_revokes_old_jwts(): void
    {
        $user = User::factory()->create();
        $oldJwt = $this->loginAs($user);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NovaSenha123', $user->password));
        $this->assertNotNull($user->password_changed_at);

        // Old JWT no longer works, not even to refresh.
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($oldJwt))->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer($oldJwt))->assertUnauthorized();

        // Old password fails, new password works.
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Senha1234'])->assertUnauthorized();
        $newJwt = $this->loginAs($user, 'NovaSenha123');
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($newJwt))->assertNoContent();

        // The reset token is single-use.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'Outra12345', 'password_confirmation' => 'Outra12345',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_reset_with_invalid_token_is_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'token-invalido',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Os dados enviados são inválidos.')
            ->assertJsonPath('errors.token.0', 'Token de redefinição inválido ou expirado.');

        $this->assertTrue(Hash::check('Senha1234', $user->fresh()->password));
    }

    public function test_reset_with_expired_token_is_422(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_reset_requires_strong_confirmed_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => Password::broker()->createToken($user),
            'password' => '12345678', 'password_confirmation' => '12345678',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
