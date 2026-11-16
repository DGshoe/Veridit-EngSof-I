<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PaymentMethod;
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

    /** Payment methods enabled in this environment (the front only offers these). Public. */
    #[OA\Get(
        path: '/api/v1/payment-methods',
        operationId: 'paymentMethods',
        summary: 'Formas de pagamento disponíveis neste ambiente',
        description: 'Só as formas configuradas aparecem (ex.: `pix` e `mercadopago` exigem as credenciais do Mercado Pago; '
            .'`simulador` só com PAYMENTS_SIMULATOR_ENABLED=true). Use para montar a escolha na tela de faturamento.',
        tags: ['Catálogo'],
        responses: [
            new OA\Response(response: 200, description: 'Formas disponíveis', content: new OA\JsonContent(
                example: ['data' => [
                    ['code' => 'pix', 'name' => 'PIX', 'description' => 'QR Code ou PIX copia e cola, aprovação em segundos.'],
                    ['code' => 'mercadopago', 'name' => 'Mercado Pago', 'description' => 'Cartão de crédito, saldo Mercado Pago ou PIX na página do Mercado Pago.'],
                ]],
            )),
        ],
    )]
    public function paymentMethods(): JsonResponse
    {
        $offered = [PaymentMethod::Pix, PaymentMethod::MercadoPago, PaymentMethod::Simulator]; // display order

        return response()->json(['data' => array_values(array_map(
            fn (PaymentMethod $method) => ['code' => $method->value, ...$method->presentation()],
            array_filter($offered, fn (PaymentMethod $method) => $method->isAvailable()),
        ))]);
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
