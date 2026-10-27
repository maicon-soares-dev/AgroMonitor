<?php

use App\Http\Middleware\VerificaPerfil;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
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

        // Não há tela de login no Laravel (o login é do front): visitante sem
        // token recebe 401 em JSON em vez de redirecionamento.
        $middleware->redirectGuestsTo(fn () => null);
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

        // Exclusão bloqueada por FK RESTRICT (ex.: safra que já tem plantio)
        $exceptions->render(function (QueryException $e, Request $request) use ($naApi) {
            if ($naApi($request) && ($e->errorInfo[1] ?? null) === 1451) {
                return response()->json([
                    'sucesso' => false,
                    'erro' => 'Não é possível excluir: este registro está sendo usado em outros cadastros.',
                ], 409);
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
