<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_api_and_database_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'commerce')
            ->assertJsonPath('data.database', 'ok');
    }

    public function test_unknown_route_returns_404_in_portuguese(): void
    {
        $this->getJson('/api/v1/nao-existe')->assertNotFound()->assertExactJson(['message' => 'Recurso não encontrado.']);
    }
}
