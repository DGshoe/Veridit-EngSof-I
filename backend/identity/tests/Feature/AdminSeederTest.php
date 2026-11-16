<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['identity.admin' => [
            'name' => 'Administrador',
            'email' => 'Admin@Veridit.local',
            'password' => 'Admin12345',
            'cpf' => '111.444.777-35',
        ]]);
    }

    public function test_creates_admin_from_configuration(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::sole();
        $this->assertSame('admin@veridit.local', $admin->email);
        $this->assertSame(UserType::Admin, $admin->type);
        $this->assertSame('11144477735', $admin->cpf);
        $this->assertTrue(Hash::check('Admin12345', $admin->password));
    }

    public function test_is_idempotent(): void
    {
        $this->seed(AdminSeeder::class);
        config(['identity.admin.password' => 'NovaSenha999']);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('NovaSenha999', User::sole()->password));
    }

    public function test_fails_without_credentials(): void
    {
        config(['identity.admin.email' => null, 'identity.admin.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminSeeder::class);
    }

    public function test_rejects_weak_password(): void
    {
        config(['identity.admin.password' => 'fraca']);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminSeeder::class);
    }
}
