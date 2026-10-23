<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera a rota só para os perfis informados.
 * Uso: ->middleware('perfil:gestor,agronomo')
 */
class VerificaPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! in_array($usuario->perfil, $perfis, true)) {
            return response()->json(['sucesso' => false, 'erro' => 'Acesso negado para o seu perfil.'], 403);
        }

        return $next($request);
    }
}
