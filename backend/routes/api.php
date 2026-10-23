<?php

use App\Http\Controllers\Api\AduboController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CulturaController;
use App\Http\Controllers\Api\PragaController;
use App\Http\Controllers\Api\SafraController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VariedadeController;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API do AgroMonitor (prefixo /api).
| Perfis: gestor | agronomo | tecnico — middleware 'perfil:...'.
*/

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Cadastros simples: qualquer perfil consulta (o técnico usa as listas no
    // monitoramento); só gestor e agrônomo gravam.
    $cadastros = [
        'clientes' => ClienteController::class,
        'safras' => SafraController::class,
        'culturas' => CulturaController::class,
        'variedades' => VariedadeController::class,
        'adubos' => AduboController::class,
        'pragas' => PragaController::class,
    ];

    foreach ($cadastros as $recurso => $controller) {
        Route::apiResource($recurso, $controller)->only(['index', 'show']);
    }

    Route::middleware('perfil:gestor,agronomo')->group(function () use ($cadastros) {
        foreach ($cadastros as $recurso => $controller) {
            Route::apiResource($recurso, $controller)->only(['store', 'update', 'destroy']);
        }
    });

    Route::middleware('perfil:gestor')->group(function () {
        Route::apiResource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario']);
    });
});
