<?php

use App\Http\Controllers\Api\AduboController;
use App\Http\Controllers\Api\AplicacaoController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ColheitaController;
use App\Http\Controllers\Api\CulturaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MonitoramentoController;
use App\Http\Controllers\Api\MonitoramentoFotoController;
use App\Http\Controllers\Api\PlantioController;
use App\Http\Controllers\Api\PontoController;
use App\Http\Controllers\Api\PragaController;
use App\Http\Controllers\Api\RelatorioController;
use App\Http\Controllers\Api\RelatorioGerencialController;
use App\Http\Controllers\Api\ResumoMonitoramentoController;
use App\Http\Controllers\Api\SafraController;
use App\Http\Controllers\Api\TalhaoController;
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

    // Áreas, talhões, pontos e plantios: qualquer perfil consulta (o técnico
    // usa no monitoramento); só gestor e agrônomo alteram.
    $estrutura = [
        'areas' => AreaController::class,
        'talhoes' => TalhaoController::class,
        'pontos' => PontoController::class,
        'plantios' => PlantioController::class,
    ];
    $parametros = ['talhoes' => 'talhao', 'pontos' => 'ponto'];

    foreach ($estrutura as $recurso => $controller) {
        Route::apiResource($recurso, $controller)->only(['index', 'show'])->parameters($parametros);
    }

    Route::middleware('perfil:gestor,agronomo')->group(function () use ($estrutura, $parametros) {
        foreach ($estrutura as $recurso => $controller) {
            Route::apiResource($recurso, $controller)->only(['store', 'update', 'destroy'])->parameters($parametros);
        }
    });

    // Dashboard e relatórios: todos os perfis consultam
    Route::get('dashboard', DashboardController::class);
    Route::controller(RelatorioController::class)->prefix('relatorios')->group(function () {
        Route::get('dias', 'dias');
        Route::get('diario', 'diario');
        Route::get('safra', 'safra');
        Route::get('comparativo', 'comparativo');
        Route::get('cliente', 'cliente');
    });

    // Relatórios gerenciais (agrupamento e pivot): gestor e agrônomo
    Route::controller(RelatorioGerencialController::class)
        ->prefix('relatorios-gerenciais')->name('relatorios-gerenciais.')
        ->middleware('perfil:gestor,agronomo')
        ->group(function () {
            Route::get('agrupamentos', 'agrupamentos')->name('agrupamentos');
            Route::get('pivot', 'pivot')->name('pivot');
        });

    // Monitoramentos: todos os perfis lançam e editam (o técnico é quem vai a
    // campo), inclusive as fotos; só gestor e agrônomo excluem o monitoramento.
    Route::get('resumo-monitoramento', ResumoMonitoramentoController::class);
    Route::apiResource('monitoramentos', MonitoramentoController::class)->except('destroy');
    Route::apiResource('monitoramento-fotos', MonitoramentoFotoController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['monitoramento-fotos' => 'foto']);
    Route::delete('monitoramentos/{monitoramento}', [MonitoramentoController::class, 'destroy'])
        ->middleware('perfil:gestor,agronomo');

    // Aplicações e colheitas: só gestor e agrônomo (o técnico não usa essas telas)
    Route::middleware('perfil:gestor,agronomo')->group(function () {
        Route::apiResource('aplicacoes', AplicacaoController::class)->parameters(['aplicacoes' => 'aplicacao']);
        Route::apiResource('colheitas', ColheitaController::class)->parameters(['colheitas' => 'colheita']);
    });

    Route::middleware('perfil:gestor')->group(function () {
        Route::apiResource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario']);
    });
});
