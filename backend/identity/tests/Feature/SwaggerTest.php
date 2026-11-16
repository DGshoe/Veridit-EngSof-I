<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SwaggerTest extends TestCase
{
    public function test_openapi_documents_every_endpoint_with_bearer_auth(): void
    {
        Artisan::call('l5-swagger:generate');
        $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

        $this->assertSame(
            ['bearerAuth' => ['type' => 'http', 'description' => 'Access token JWT obtido em /api/v1/auth/login.', 'bearerFormat' => 'JWT', 'scheme' => 'bearer']],
            $spec['components']['securitySchemes'],
        );
        foreach (['User', 'TokenResponse', 'ValidationError', 'Error'] as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas']);
        }

        $protected = [
            'post /api/v1/auth/logout', 'post /api/v1/auth/refresh',
            'get /api/v1/me', 'get /api/v1/users', 'get /api/v1/users/{id}',
        ];
        $public = [
            'get /api/v1/health', 'post /api/v1/auth/register', 'post /api/v1/auth/login',
            'post /api/v1/auth/forgot-password', 'post /api/v1/auth/reset-password',
        ];

        foreach ([...$protected, ...$public] as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            $this->assertArrayHasKey($method, $spec['paths'][$path] ?? [], "Endpoint não documentado: {$endpoint}");

            $security = $spec['paths'][$path][$method]['security'] ?? null;
            in_array($endpoint, $protected, true)
                ? $this->assertSame([['bearerAuth' => []]], $security, "{$endpoint} deveria exigir bearerAuth")
                : $this->assertNull($security, "{$endpoint} deveria ser público");
        }
    }

    public function test_documentation_page_is_available(): void
    {
        Artisan::call('l5-swagger:generate');

        $this->get('/api/documentation')->assertOk()->assertSee('swagger-ui', false);
    }
}
