<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

class HealthController extends Controller
{
    /** API up + commerce database reachable. Used by the deploy. */
    #[OA\Get(
        path: '/api/v1/health',
        operationId: 'health',
        summary: 'Status da API e do banco commerce',
        tags: ['Saúde'],
        responses: [
            new OA\Response(response: 200, description: 'API e banco disponíveis', content: new OA\JsonContent(
                example: ['data' => ['status' => 'ok', 'service' => 'commerce', 'database' => 'ok', 'timestamp' => '2026-11-10T12:00:00+00:00']],
            )),
            new OA\Response(response: 503, description: 'Banco indisponível', content: new OA\JsonContent(
                example: ['data' => ['status' => 'degradado', 'service' => 'commerce', 'database' => 'indisponível', 'timestamp' => '2026-11-10T12:00:00+00:00']],
            )),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $databaseOk = $this->databaseIsReachable();

        return response()->json([
            'data' => [
                'status' => $databaseOk ? 'ok' : 'degradado',
                'service' => 'commerce',
                'database' => $databaseOk ? 'ok' : 'indisponível',
                'timestamp' => now()->toIso8601String(),
            ],
        ], $databaseOk ? 200 : 503);
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (Throwable $e) {
            Log::warning('Health check: banco commerce indisponível.', ['exception' => $e::class]);

            return false;
        }
    }
}
