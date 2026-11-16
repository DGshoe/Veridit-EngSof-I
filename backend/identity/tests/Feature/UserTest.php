<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // ---- /me (Consulta 1) ----------------------------------------------

    public function test_me_returns_authenticated_user_with_masked_cpf(): void
    {
        $user = User::factory()->advogado()->create(['cpf' => '52998224725']);

        $this->getJson('/api/v1/me', $this->bearer($this->loginAs($user)))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.cpf', '***.982.247-**')
            ->assertJsonPath('data.oab', $user->oab())
            ->assertJsonMissingPath('data.password');
    }

    public function test_me_without_token_is_401(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Não autenticado.']);
    }

    public function test_me_with_invalid_token_is_401(): void
    {
        $this->getJson('/api/v1/me', $this->bearer('abc.def.ghi'))->assertUnauthorized();
    }

    // ---- /users (Consulta 2) -------------------------------------------

    public function test_admin_lists_users_paginated_newest_first(): void
    {
        $admin = User::factory()->admin()->create(['created_at' => now()->subDays(10)]);
        User::factory()->count(20)->sequence(fn ($s) => ['created_at' => now()->subMinutes(100 - $s->index)])->create();
        $newest = User::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/v1/users?per_page=5&page=1', $this->bearer($this->loginAs($admin)))
            ->assertOk()
            ->assertExactJsonStructure([
                'data' => ['*' => ['id', 'name', 'email', 'cpf', 'type', 'oab', 'status', 'last_login_at', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 5, 'total' => 22, 'last_page' => 5])
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonCount(5, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertMatchesRegularExpression('/^\*\*\*\.\d{3}\.\d{3}-\*\*$/', $row['cpf']);
        }

        $this->getJson('/api/v1/users?per_page=5&page=5', $this->bearer($this->loginAs($admin)))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 5);
    }

    public function test_default_page_size_is_15(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(16)->create();

        $this->getJson('/api/v1/users', $this->bearer($this->loginAs($admin)))
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.per_page', 15);
    }

    public function test_filters_by_type_status_and_search(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@veridit.local']);
        $lawyer = User::factory()->advogado()->create(['name' => 'Maria Souza', 'email' => 'maria@exemplo.com']);
        $blocked = User::factory()->bloqueado()->create(['name' => 'João Bloqueado', 'email' => 'joao@exemplo.com']);
        User::factory()->create(['name' => 'Carlos Lima', 'email' => 'carlos@outro.com']);
        $auth = $this->bearer($this->loginAs($admin));

        $ids = fn (string $query) => collect($this->getJson("/api/v1/users?{$query}", $auth)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$lawyer->id], $ids('type=advogado'));
        $this->assertSame([$blocked->id], $ids('status=bloqueado'));
        $this->assertSame([$lawyer->id], $ids('search=maria'));          // name, case-insensitive
        $this->assertSame([$lawyer->id], $ids('search=MARIA%40exemplo'));  // e-mail
        $this->assertEqualsCanonicalizing([$lawyer->id, $blocked->id], $ids('search=exemplo.com'));
        $this->assertSame([], $ids('type=advogado&status=bloqueado'));
    }

    public function test_invalid_filters_are_422(): void
    {
        $auth = $this->bearer($this->loginAs(User::factory()->admin()->create()));

        $this->getJson('/api/v1/users?per_page=51', $auth)->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/v1/users?type=empresa', $auth)->assertUnprocessable()->assertJsonValidationErrors(['type']);
        $this->getJson('/api/v1/users?status=x', $auth)->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_common_user_and_lawyer_cannot_list_users(): void
    {
        foreach ([User::factory()->create(), User::factory()->advogado()->create()] as $user) {
            $this->getJson('/api/v1/users', $this->bearer($this->loginAs($user)))
                ->assertForbidden()
                ->assertExactJson(['message' => 'Acesso negado.']);
        }
    }

    public function test_list_without_token_is_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    // ---- /users/{id} ---------------------------------------------------

    public function test_admin_sees_any_user(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->advogado()->create();

        $this->getJson("/api/v1/users/{$other->id}", $this->bearer($this->loginAs($admin)))
            ->assertOk()->assertJsonPath('data.id', $other->id);
    }

    public function test_user_sees_only_themself(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $auth = $this->bearer($this->loginAs($user));

        $this->getJson("/api/v1/users/{$user->id}", $auth)->assertOk()->assertJsonPath('data.id', $user->id);
        $this->getJson("/api/v1/users/{$other->id}", $auth)->assertForbidden()->assertExactJson(['message' => 'Acesso negado.']);
    }

    public function test_unknown_or_malformed_id_is_404(): void
    {
        $auth = $this->bearer($this->loginAs(User::factory()->admin()->create()));

        $this->getJson('/api/v1/users/'.Str::uuid(), $auth)->assertNotFound()->assertExactJson(['message' => 'Recurso não encontrado.']);
        $this->getJson('/api/v1/users/123', $auth)->assertNotFound();
    }

    public function test_detail_without_token_is_401(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/v1/users/{$user->id}")->assertUnauthorized();
    }
}
