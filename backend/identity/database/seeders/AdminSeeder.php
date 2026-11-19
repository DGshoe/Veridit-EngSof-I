<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Support\Cpf;
use App\Support\Mask;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or updates) the administrator from ADMIN_* environment variables.
 * Idempotent: running it again updates the same admin, matched by e-mail.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('identity.admin');

        $email = mb_strtolower(trim((string) ($config['email'] ?? '')));
        $password = (string) ($config['password'] ?? '');
        $cpf = Cpf::normalize($config['cpf'] ?? '');

        $this->validate($email, $password, $cpf);

        $admin = User::query()->where('email', $email)->first() ?? new User;

        $admin->forceFill([
            'name' => $config['name'] ?: 'Administrador',
            'email' => $email,
            'password' => $password,
            'cpf' => $cpf,
            'type' => UserType::Admin,
            'oab_number' => null,
            'oab_uf' => null,
            'status' => UserStatus::Ativo,
        ])->save();

        // Never print the password or the full CPF/e-mail.
        $this->command?->info(sprintf(
            'Admin %s: %s',
            $admin->wasRecentlyCreated ? 'criado' : 'atualizado',
            Mask::email($email),
        ));
    }

    private function validate(string $email, string $password, string $cpf): void
    {
        $errors = [];

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ADMIN_EMAIL ausente ou inválido.';
        }
        if (strlen($password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $errors[] = 'ADMIN_PASSWORD ausente ou fraca (mínimo 8 caracteres, com letras e números).';
        }
        if (! Cpf::isValid($cpf)) {
            $errors[] = 'ADMIN_CPF ausente ou inválido.';
        }
        if ($cpf !== '' && User::query()->where('cpf', $cpf)->where('email', '!=', $email)->exists()) {
            $errors[] = 'ADMIN_CPF já pertence a outro usuário.';
        }

        if ($errors) {
            throw new RuntimeException("AdminSeeder não executado:\n - ".implode("\n - ", $errors));
        }
    }
}
