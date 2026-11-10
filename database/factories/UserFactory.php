<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Default plain-text password for factory users (tests only). */
    public const PASSWORD = 'Senha1234';

    protected static ?string $passwordHash;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'cpf' => self::validCpf(),
            'type' => UserType::Comum,
            'oab_number' => null,
            'oab_uf' => null,
            'status' => UserStatus::Ativo,
        ];
    }

    public function advogado(): static
    {
        return $this->state(fn () => [
            'type' => UserType::Advogado,
            'oab_number' => (string) fake()->unique()->numberBetween(1000, 99999999),
            'oab_uf' => fake()->randomElement(['BA', 'SP', 'RJ', 'MG', 'PE']),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['type' => UserType::Admin]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Bloqueado]);
    }

    /** Random CPF with valid check digits (digits only). */
    public static function validCpf(): string
    {
        do {
            $base = (string) fake()->unique()->numerify('#########');
        } while (preg_match('/^(\d)\1{8}$/', $base));

        return $base.Cpf::checkDigits($base);
    }
}
