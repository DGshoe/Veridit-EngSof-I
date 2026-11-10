<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_api_and_database_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'identity')
            ->assertJsonPath('data.database', 'ok');
    }

    public function test_health_returns_503_when_database_is_down(): void
    {
        config(['database.connections.identity.database' => '/caminho/inexistente/identity.sqlite']);
        DB::purge('identity');

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'degradado')
            ->assertJsonPath('data.database', 'indisponível');
    }
}
