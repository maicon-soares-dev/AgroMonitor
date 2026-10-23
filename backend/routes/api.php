<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API do AgroMonitor (prefixo /api).
| Perfis: gestor | agronomo | tecnico — middleware 'perfil:...'.
*/

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::middleware('perfil:gestor')->group(function () {
        Route::apiResource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario']);
    });
});
