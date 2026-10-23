<?php

use App\Http\Middleware\VerificaPerfil;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perfil' => VerificaPerfil::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Na API, todo erro sai no formato do AgroMonitor:
        // {"sucesso": false, "erro": "...", "erros": {"campo": ["mensagem"]}}
        $naApi = fn (Request $request) => $request->is('api/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $naApi($request) || $request->expectsJson());

        $exceptions->render(function (ValidationException $e, Request $request) use ($naApi) {
            if (! $naApi($request)) {
                return null;
            }

            return response()->json([
                'sucesso' => false,
                'erro' => $e->validator->errors()->first(),
                'erros' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($naApi) {
            if ($naApi($request)) {
                return response()->json(['sucesso' => false, 'erro' => 'Não autenticado. Faça login novamente.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($naApi) {
            if ($naApi($request)) {
                return response()->json(['sucesso' => false, 'erro' => 'Acesso negado para o seu perfil.'], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($naApi) {
            if ($naApi($request)) {
                return response()->json(['sucesso' => false, 'erro' => 'Registro ou rota não encontrado.'], 404);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($naApi) {
            if (! $naApi($request)) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $mensagem = $status === 500 && ! config('app.debug') ? 'Erro interno no servidor.' : $e->getMessage();

            return response()->json(['sucesso' => false, 'erro' => $mensagem ?: 'Erro na requisição.'], $status);
        });
    })->create();
