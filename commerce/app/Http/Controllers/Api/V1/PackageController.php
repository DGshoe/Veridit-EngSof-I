<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PackageController extends Controller
{
    /** D2 catalog (REQ05). Public. */
    #[OA\Get(
        path: '/api/v1/packages',
        operationId: 'listPackages',
        summary: 'Catálogo de pacotes (D2)',
        description: 'Pacotes ativos, do menor para o maior. Público.',
        tags: ['Catálogo'],
        responses: [
            new OA\Response(response: 200, description: 'Pacotes', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Package'))],
            )),
        ],
    )]
    public function index(): JsonResponse
    {
        $packages = Package::query()->active()->orderBy('credits')->get();

        return response()->json(['data' => PackageResource::collection($packages)]);
    }

    /** Credits consumed by each capture type. Public. */
    #[OA\Get(
        path: '/api/v1/credits/costs',
        operationId: 'captureCosts',
        summary: 'Custo de cada tipo de captura, em créditos',
        tags: ['Catálogo'],
        responses: [
            new OA\Response(response: 200, description: 'Custos', content: new OA\JsonContent(
                example: ['data' => ['imagem' => 1, 'video' => 2]],
            )),
        ],
    )]
    public function captureCosts(): JsonResponse
    {
        return response()->json(['data' => config('commerce.capture_costs')]);
    }
}
