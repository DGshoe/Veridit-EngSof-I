<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * §7: 500 without stack trace in production. §6 / ADR-012: logs never carry
 * passwords, tokens or the full CPF.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]); // as in production
        Event::listen(MessageLogged::class, fn (MessageLogged $e) => $this->logged[] = $e);
    }

    public function test_unexpected_error_returns_generic_500_without_details(): void
    {
        Route::get('/api/v1/_boom', fn () => throw new RuntimeException('detalhe interno secreto'));

        $this->getJson('/api/v1/_boom')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Erro interno do servidor.']);
    }

    public function test_database_error_is_logged_without_personal_data(): void
    {
        $existing = User::factory()->create(['cpf' => '52998224725']);

        // Simulates the race condition: insert bypassing validation -> unique violation.
        Route::post('/api/v1/_duplicate', fn () => User::create([
            'name' => 'Duplicado', 'email' => 'duplicado@exemplo.com', 'password' => 'Senha1234',
            'cpf' => $existing->cpf, 'type' => 'comum',
        ]));

        $this->postJson('/api/v1/_duplicate')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Erro interno do servidor.']);

        $log = json_encode(array_map(fn (MessageLogged $e) => [$e->message, $e->context], $this->logged));

        $this->assertStringContainsString('Erro de banco de dados.', $log);
        $this->assertStringNotContainsString('52998224725', $log, 'CPF completo no log');
        $this->assertStringNotContainsString('duplicado@exemplo.com', $log, 'e-mail no log');
        $this->assertStringNotContainsString('$2y$', $log, 'hash de senha no log');
        $this->assertStringNotContainsString('Senha1234', $log, 'senha no log');
    }

    public function test_stack_traces_never_include_function_arguments(): void
    {
        $this->assertSame('1', ini_get('zend.exception_ignore_args'),
            'zend.exception_ignore_args deve estar On (docker/php/conf.d/security.ini)');

        $throw = fn (string $password) => throw new RuntimeException('falha');

        try {
            $throw('SenhaSecreta123');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('SenhaSecreta123', $e->getTraceAsString());
        }
    }
}
