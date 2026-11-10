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

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** REQ05: buy a package. 201 when created; 200 when replayed with the same Idempotency-Key. */
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

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json(['data' => new OrderResource($order)]);
    }
}
