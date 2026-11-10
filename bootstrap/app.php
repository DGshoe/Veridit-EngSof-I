<?php

use App\Exceptions\Auth\AccountBlockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Http\Middleware\EnsureTokenMatchesPassword;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserType;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'type' => EnsureUserType::class,
        ]);

        // Authenticated API routes: valid JWT issued with the current password,
        // belonging to an active (not blocked) user.
        $middleware->group('jwt', [
            'auth:api',
            EnsureTokenMatchesPassword::class,
            EnsureUserIsActive::class,
        ]);

        // API only: never redirect unauthenticated requests to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected business outcomes, not errors: do not log them.
        $exceptions->dontReport([
            InvalidCredentialsException::class,
            AccountBlockedException::class,
            InvalidTokenException::class,
        ]);

        // Never write these request fields to logs/flash data (§6 privacy).
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'cpf']);

        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Standard error body (§7): { "message": "..." } in Portuguese.
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'message' => 'Os dados enviados são inválidos.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $message = match ($e->getStatusCode()) {
                401 => 'Não autenticado.',
                403 => 'Acesso negado.',
                404 => 'Recurso não encontrado.',
                405 => 'Método não permitido.',
                429 => 'Muitas tentativas. Tente novamente em instantes.',
                503 => 'Serviço temporariamente indisponível.',
                default => $e->getStatusCode() >= 500 ? 'Erro interno do servidor.' : 'Requisição inválida.',
            };

            return response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });

        // Any other error: generic 500 without stack trace (unless APP_DEBUG=true in dev).
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($e instanceof HttpResponseException) {
                return null; // already carries its own response
            }
            if ($isApi($request) && ! config('app.debug')) {
                return response()->json(['message' => 'Erro interno do servidor.'], 500);
            }
        });
    })->create();
