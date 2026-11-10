<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** REQ01 */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Usuário cadastrado com sucesso.'])
            ->response()
            ->setStatusCode(201);
    }

    /** REQ03 */
    public function login(LoginRequest $request): TokenResource
    {
        return new TokenResource(
            $this->auth->login($request->validated('email'), $request->validated('password'))
        );
    }

    /** REQ04 */
    public function logout(): Response
    {
        $this->auth->logout();

        return response()->noContent();
    }

    public function refresh(): TokenResource
    {
        return new TokenResource($this->auth->refresh());
    }
}
