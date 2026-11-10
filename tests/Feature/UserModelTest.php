<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_data_and_applies_defaults(): void
    {
        $user = User::create([
            'name' => 'Maria Souza',
            'email' => '  Maria@Exemplo.COM ',
            'password' => 'Senha1234',
            'cpf' => '529.982.247-25',
            'type' => UserType::Advogado,
            'oab_number' => '123456',
            'oab_uf' => 'ba',
        ])->fresh();

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertSame('maria@exemplo.com', $user->email);
        $this->assertSame('52998224725', $user->cpf);
        $this->assertSame('123456/BA', $user->oab());
        $this->assertSame(UserStatus::Ativo, $user->status);
        $this->assertNotSame('Senha1234', $user->password);
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_cpf_must_be_unique(): void
    {
        User::factory()->create(['cpf' => '52998224725']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['cpf' => '529.982.247-25']);
    }

    public function test_oab_number_and_uf_pair_must_be_unique(): void
    {
        User::factory()->advogado()->create(['oab_number' => '123456', 'oab_uf' => 'BA']);
        User::factory()->advogado()->create(['oab_number' => '123456', 'oab_uf' => 'SP']); // other UF: ok
        User::factory()->count(2)->create(); // common users without OAB: NULLs do not collide

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->advogado()->create(['oab_number' => '123456', 'oab_uf' => 'BA']);
    }
}
