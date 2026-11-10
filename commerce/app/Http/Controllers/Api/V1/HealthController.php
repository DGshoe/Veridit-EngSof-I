<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    /** API up + commerce database reachable. Used by the deploy. */
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
