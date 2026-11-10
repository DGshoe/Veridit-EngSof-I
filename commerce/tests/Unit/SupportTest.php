<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\PemKey;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_money_formats_brl(): void
    {
        $this->assertSame('R$ 30,00', Money::brl(3000));
        $this->assertSame('R$ 2,60', Money::brl(260));
        $this->assertSame('R$ 1.200,50', Money::brl(120050));
        $this->assertSame('R$ 0,00', Money::brl(0));
    }

    public function test_pem_key_accepts_base64_and_escaped_newlines(): void
    {
        $pem = "-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----\n";

        $this->assertSame($pem, PemKey::fromEnv('base64:'.base64_encode($pem)));
        $this->assertSame($pem, PemKey::fromEnv(str_replace("\n", '\n', $pem)));
        $this->assertNull(PemKey::fromEnv(''));
        $this->assertNull(PemKey::fromEnv(null));
        $this->assertNull(PemKey::fromEnv('base64:%%%invalido%%%'));
    }
}
