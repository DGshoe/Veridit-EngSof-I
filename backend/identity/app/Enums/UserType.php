<?php

namespace App\Enums;

/**
 * User profiles (D1: first version supports "advogado" and "comum").
 *
 * "admin" is never self-registered: it only comes from AdminSeeder.
 * "empresa" is RESERVED for a future version and intentionally is not a case
 * yet; registration must reject it with a specific message (see RESERVED).
 */
enum UserType: string
{
    case Advogado = 'advogado';
    case Comum = 'comum';
    case Admin = 'admin';

    /** Types planned for the future, not accepted yet. */
    public const RESERVED = ['empresa'];

    /**
     * Types a visitor may choose on public registration (REQ01).
     *
     * @return list<self>
     */
    public static function selfRegistrable(): array
    {
        return [self::Advogado, self::Comum];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Advogado => 'Advogado',
            self::Comum => 'Usuário comum',
            self::Admin => 'Administrador',
        };
    }
}
