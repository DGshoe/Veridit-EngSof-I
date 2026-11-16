<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'service' => \App\Http\Middleware\EnsureServiceToken::class,
        ]);

        // API only: never redirect unauthenticated requests to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Database errors: the default message embeds the SQL bindings. Log a sanitized version.
        $exceptions->report(function (QueryException $e) {
            Log::error('Erro de banco de dados.', [
                'exception' => $e::class,
                'sqlstate' => $e->getCode(),
                'connection' => $e->connectionName,
                'sql' => $e->getSql(),
                'at' => $e->getFile().':'.$e->getLine(),
            ]);

            return false;
        });

        $exceptions->dontFlash(['token', 'cpf']);

        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

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
                409 => 'Conflito com o estado atual do recurso.',
                429 => 'Muitas tentativas. Tente novamente em instantes.',
                503 => 'Serviço temporariamente indisponível.',
                default => $e->getStatusCode() >= 500 ? 'Erro interno do servidor.' : 'Requisição inválida.',
            };

            return response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($e instanceof HttpResponseException) {
                return null;
            }
            if ($isApi($request) && ! config('app.debug')) {
                return response()->json(['message' => 'Erro interno do servidor.'], 500);
            }
        });
    })->create();
