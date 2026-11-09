<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    /**
     * Reports whether the API is up and the identity database is reachable.
     * Used by the deploy hook to confirm a release is healthy.
     */
    public function __invoke(): JsonResponse
    {
        $databaseOk = $this->databaseIsReachable();

        return response()->json([
            'data' => [
                'status' => $databaseOk ? 'ok' : 'degradado',
                'service' => 'identity',
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
            // Log only the exception class: connection errors can carry DSN details.
            Log::warning('Health check: banco identity indisponível.', ['exception' => $e::class]);

            return false;
        }
    }
}
