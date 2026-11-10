<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function lawyerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Souza',
            'email' => 'maria@exemplo.com',
            'password' => 'Senha1234',
            'password_confirmation' => 'Senha1234',
            'cpf' => '529.982.247-25',
            'type' => 'advogado',
            'oab_number' => '123456',
            'oab_uf' => 'BA',
        ], $overrides);
    }

    // ---- REQ01: registration -------------------------------------------

    public function test_registers_lawyer(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload())
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria@exemplo.com')
            ->assertJsonPath('data.cpf', '***.982.247-**')
            ->assertJsonPath('data.type', 'advogado')
            ->assertJsonPath('data.oab', '123456/BA')
            ->assertJsonPath('data.status', 'ativo')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.access_token'); // no automatic login

        $this->assertDatabaseHas('users', ['email' => 'maria@exemplo.com', 'cpf' => '52998224725']);
    }

    public function test_registers_common_user_ignoring_oab_fields(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload([
            'type' => 'comum', 'cpf' => '11144477735',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'comum')
            ->assertJsonPath('data.oab', null);

        $this->assertDatabaseHas('users', ['email' => 'maria@exemplo.com', 'oab_number' => null, 'oab_uf' => null]);
    }

    public function test_lawyer_without_oab_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['oab_number' => null, 'oab_uf' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Os dados enviados são inválidos.')
            ->assertJsonValidationErrors(['oab_number', 'oab_uf']);
    }

    public function test_invalid_oab_uf_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['oab_uf' => 'XX']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['oab_uf']);
    }

    public function test_invalid_cpf_is_rejected(): void
    {
        foreach (['529.982.247-26', '111.111.111-11', 'abc'] as $cpf) {
            $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['cpf' => $cpf]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.cpf.0', 'CPF inválido.');
        }
    }

    public function test_duplicated_cpf_is_rejected(): void
    {
        User::factory()->create(['cpf' => '52998224725']);

        $this->postJson('/api/v1/auth/register', $this->lawyerPayload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'Este CPF já está cadastrado.');
    }

    public function test_duplicated_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'maria@exemplo.com']);

        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['email' => 'MARIA@exemplo.com']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado.');
    }

    public function test_duplicated_oab_is_rejected(): void
    {
        User::factory()->advogado()->create(['oab_number' => '123456', 'oab_uf' => 'BA']);

        $this->postJson('/api/v1/auth/register', $this->lawyerPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['oab_number']);
    }

    public function test_company_type_is_not_available_yet(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['type' => 'empresa']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Cadastro de empresas ainda não disponível.');
    }

    public function test_admin_cannot_self_register(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['type' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->lawyerPayload([
            'password' => 'somenteletras', 'password_confirmation' => 'somenteletras',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->postJson('/api/v1/auth/register', $this->lawyerPayload(['password_confirmation' => 'Outra1234']))
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    // ---- REQ03: login --------------------------------------------------

    public function test_login_returns_token(): void
    {
        $user = User::factory()->advogado()->create();

        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'Senha1234'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'cpf', 'type', 'oab']]])
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_401(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Errada123'])
            ->assertUnauthorized()->json('message');
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@exemplo.com', 'password' => 'Errada123'])
            ->assertUnauthorized()->json('message');

        $this->assertSame('E-mail ou senha inválidos.', $wrongPassword);
        $this->assertSame($wrongPassword, $unknownEmail);
    }

    public function test_blocked_user_gets_403(): void
    {
        $user = User::factory()->bloqueado()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Senha1234'])
            ->assertForbidden();
    }

    public function test_blocking_takes_effect_immediately_on_existing_tokens(): void
    {
        $user = User::factory()->create();
        $token = $this->loginAs($user);
        $this->getJson('/api/v1/me', $this->bearer($token))->assertOk();

        $user->forceFill(['status' => 'bloqueado'])->save();

        $this->getJson('/api/v1/me', $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer($token))->assertForbidden();
    }

    public function test_sixth_login_attempt_in_a_minute_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Errada123'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Senha1234'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Muitas tentativas. Tente novamente em instantes.');

        // Limit is per IP + e-mail: another e-mail is not affected.
        $other = User::factory()->create();
        $this->postJson('/api/v1/auth/login', ['email' => $other->email, 'password' => 'Senha1234'])->assertOk();
    }

    // ---- REQ04: logout -------------------------------------------------

    public function test_logout_invalidates_token(): void
    {
        $token = $this->loginAs(User::factory()->create());

        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))->assertNoContent();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_logout_without_token_is_401(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    // ---- Refresh -------------------------------------------------------

    public function test_refresh_returns_new_token_and_old_one_stops_working(): void
    {
        $user = User::factory()->create();
        $old = $this->loginAs($user);

        $new = $this->postJson('/api/v1/auth/refresh', [], $this->bearer($old))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->json('data.access_token');

        $this->assertNotSame($old, $new);
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer($old))->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($old))->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($new))->assertNoContent();
    }

    public function test_expired_token_can_be_refreshed_inside_the_7_day_window_only(): void
    {
        $user = User::factory()->create();

        $token = $this->loginAs($user);
        $this->travel(2)->days();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))->assertUnauthorized(); // expired
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer($token))->assertOk();

        $this->travelBack();
        $token = $this->loginAs($user);
        $this->travel(8)->days();
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer($token))->assertUnauthorized();
    }

    public function test_refresh_keeps_profile_claim(): void
    {
        $admin = User::factory()->admin()->create();
        $new = $this->postJson('/api/v1/auth/refresh', [], $this->bearer($this->loginAs($admin)))->json('data.access_token');

        $payload = json_decode(base64_decode(strtr(explode('.', $new)[1], '-_', '+/')), true);
        $this->assertSame('admin', $payload['type']);
        $this->assertArrayNotHasKey('cpf', $payload);
    }

    public function test_refresh_with_garbage_token_is_401(): void
    {
        $this->postJson('/api/v1/auth/refresh', [], $this->bearer('nao.e.um.jwt'))->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    // ---- RBAC ----------------------------------------------------------

    public function test_type_middleware_enforces_profile(): void
    {
        Route::middleware(['api', 'auth:api', 'type:admin'])->get('/api/v1/_rbac-probe', fn () => ['ok' => true]);

        $this->getJson('/api/v1/_rbac-probe')->assertUnauthorized();
        $this->getJson('/api/v1/_rbac-probe', $this->bearer($this->loginAs(User::factory()->create())))
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso negado.');
        $this->getJson('/api/v1/_rbac-probe', $this->bearer($this->loginAs(User::factory()->admin()->create())))
            ->assertOk();
    }
}
