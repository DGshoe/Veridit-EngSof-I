<?php

namespace Tests\Unit;

use App\Support\Cpf;
use App\Support\Mask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfAndMaskTest extends TestCase
{
    public static function validCpfs(): array
    {
        return [['529.982.247-25'], ['52998224725'], ['111.444.777-35']];
    }

    public static function invalidCpfs(): array
    {
        return [['529.982.247-26'], ['111.111.111-11'], ['00000000000'], ['1234567890'], [''], ['abc']];
    }

    #[DataProvider('validCpfs')]
    public function test_accepts_valid_cpf(string $cpf): void
    {
        $this->assertTrue(Cpf::isValid($cpf));
    }

    #[DataProvider('invalidCpfs')]
    public function test_rejects_invalid_cpf(string $cpf): void
    {
        $this->assertFalse(Cpf::isValid($cpf));
    }

    public function test_normalize_keeps_only_digits(): void
    {
        $this->assertSame('52998224725', Cpf::normalize('529.982.247-25'));
    }

    public function test_masks_cpf(): void
    {
        $this->assertSame('***.982.247-**', Mask::cpf('52998224725'));
        $this->assertNull(Mask::cpf(null));
    }

    public function test_masks_email(): void
    {
        $this->assertSame('m***@exemplo.com', Mask::email('maria@exemplo.com'));
    }
}
