<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaginatedCollection;
use App\Models\Order;
use App\Models\Package;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** REQ05: buy a package. 201 when created; 200 when replayed with the same Idempotency-Key. */
    #[OA\Post(
        path: '/api/v1/orders',
        operationId: 'createOrder',
        summary: 'Comprar um pacote (REQ05)',
        description: 'Cria o pedido **pendente** e abre a cobrança no provedor. Os créditos só entram quando o provedor '
            .'confirma o pagamento. Dados de faturamento vêm do cadastro no Identity.'
            ."\n\nEnvie `Idempotency-Key` para evitar compra duplicada: repetir a mesma requisição devolve o mesmo pedido (200).",
        security: [['bearerAuth' => []]],
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: false, description: '8 a 100 caracteres (letras, números, - _ : .)',
                schema: new OA\Schema(type: 'string', example: 'compra-7f3a9c2e-0001')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['package', 'payment_method'],
            properties: [
                new OA\Property(property: 'package', type: 'string', enum: ['basico', 'medio', 'premium'], example: 'medio'),
                new OA\Property(property: 'payment_method', type: 'string', enum: ['simulador', 'mercadopago', 'pix'],
                    description: 'Disponível hoje: simulador (quando PAYMENTS_SIMULATOR_ENABLED=true).', example: 'simulador'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Pedido criado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
                new OA\Property(property: 'message', type: 'string', example: 'Pedido criado. Aguardando pagamento.'),
            ])),
            new OA\Response(response: 200, description: 'Mesma Idempotency-Key: pedido já existente', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
                new OA\Property(property: 'message', type: 'string', example: 'Pedido já registrado com esta Idempotency-Key.'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(response: 409, description: 'Idempotency-Key reutilizada com outros dados', content: new OA\JsonContent(ref: '#/components/schemas/Error',
                example: ['message' => 'Esta Idempotency-Key já foi usada em outro pedido com dados diferentes.'])),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(response: 503, description: 'Identity ou provedor de pagamento indisponível (nenhum valor cobrado)', content: new OA\JsonContent(ref: '#/components/schemas/Error',
                example: ['message' => 'Não foi possível obter seus dados de faturamento. Tente novamente em instantes.'])),
        ],
    )]
    public function store(CreateOrderRequest $request): JsonResponse
    {
        [$order, $created] = $this->orders->create(
            user: $request->user(),
            package: Package::query()->where('code', $request->validated('package'))->firstOrFail(),
            method: PaymentMethod::from($request->validated('payment_method')),
            idempotencyKey: $request->idempotencyKey(),
        );

        return response()->json([
            'data' => new OrderResource($order),
            'message' => $created ? 'Pedido criado. Aguardando pagamento.' : 'Pedido já registrado com esta Idempotency-Key.',
        ], $created ? 201 : 200);
    }

    /** Own orders (admin: all), newest first. */
    #[OA\Get(
        path: '/api/v1/orders',
        operationId: 'listOrders',
        summary: 'Listar pedidos',
        description: 'Usuário: só os próprios pedidos. Admin: todos. Mais recentes primeiro.',
        security: [['bearerAuth' => []]],
        tags: ['Pedidos'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pendente', 'pago', 'falhou'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 50, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function index(ListOrdersRequest $request): PaginatedCollection
    {
        $user = $request->user();

        $orders = Order::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return new PaginatedCollection($orders, OrderResource::class);
    }

    #[OA\Get(
        path: '/api/v1/orders/{id}',
        operationId: 'showOrder',
        summary: 'Detalhe de um pedido',
        description: 'Permitido ao dono do pedido ou a um admin.',
        security: [['bearerAuth' => []]],
        tags: ['Pedidos'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Pedido', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ],
    )]
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json(['data' => new OrderResource($order)]);
    }
}
