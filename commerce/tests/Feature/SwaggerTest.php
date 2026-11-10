<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SwaggerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function spec(): array
    {
        Artisan::call('l5-swagger:generate');

        return json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);
    }

    public function test_every_endpoint_is_documented_with_the_right_security(): void
    {
        $spec = $this->spec();

        $this->assertSame(['bearerAuth', 'serviceToken', 'simulatorSignature', 'mercadoPagoSignature'], array_keys($spec['components']['securitySchemes']));
        foreach (['Package', 'Order', 'LedgerEntry', 'ValidationError', 'Error', 'PaginationMeta'] as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas']);
        }

        $expected = [
            'get /api/v1/health' => null,
            'get /api/v1/packages' => null,
            'get /api/v1/credits/costs' => null,
            'post /api/v1/orders' => 'bearerAuth',
            'get /api/v1/orders' => 'bearerAuth',
            'get /api/v1/orders/{id}' => 'bearerAuth',
            'post /api/v1/orders/{id}/simulate' => 'bearerAuth',
            'post /api/v1/webhooks/simulator' => 'simulatorSignature',
            'post /api/v1/webhooks/mercadopago' => 'mercadoPagoSignature',
            'get /api/v1/wallet' => 'bearerAuth',
            'get /api/v1/wallet/entries' => 'bearerAuth',
            'post /api/v1/internal/credits/consume' => 'serviceToken',
        ];

        foreach ($expected as $endpoint => $scheme) {
            [$method, $path] = explode(' ', $endpoint);
            $operation = $spec['paths'][$path][$method] ?? null;
            $this->assertNotNull($operation, "Endpoint não documentado: {$endpoint}");
            $this->assertSame($scheme ? [[$scheme => []]] : null, $operation['security'] ?? null, "Segurança errada em {$endpoint}");
        }
    }

    public function test_every_api_route_appears_in_the_documentation(): void
    {
        $documented = [];
        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/'.preg_replace('/\{order\}/', '{id}', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertContains("{$method} {$path}", $documented, "Rota sem documentação: {$method} {$path}");
            }
        }
    }

    public function test_documentation_page_is_available(): void
    {
        Artisan::call('l5-swagger:generate');

        $this->get('/api/documentation')->assertOk()->assertSee('swagger-ui', false);
    }
}
