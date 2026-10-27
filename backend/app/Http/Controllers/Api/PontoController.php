<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PontoRequest;
use App\Models\PontoMonitoramento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pontos de monitoramento da área, numerados. */
class PontoController extends Controller
{
    /** GET /api/pontos?area_id= — ordenados pelo número. */
    public function index(Request $request): JsonResponse
    {
        $pontos = PontoMonitoramento::query()
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->area_id))
            ->orderBy('area_id')
            ->orderBy('numero_ponto')
            ->get();

        return $this->dados($pontos);
    }

    public function show(PontoMonitoramento $ponto): JsonResponse
    {
        return $this->dados($ponto);
    }

    public function store(PontoRequest $request): JsonResponse
    {
        $ponto = PontoMonitoramento::create($request->validated() + ['data_registro' => now()->toDateString()]);

        return $this->criado($ponto->id);
    }

    /** Edição parcial: só muda o que veio no corpo (posição, número, talhão ou observação). */
    public function update(PontoRequest $request, PontoMonitoramento $ponto): JsonResponse
    {
        $campos = $request->safe()->only(['talhao_id', 'numero_ponto', 'latitude', 'longitude', 'observacoes']);
        if (! $campos) {
            return $this->erro('Nada para atualizar');
        }

        $ponto->update($campos);

        return $this->ok();
    }

    /** Apaga o ponto e os monitoramentos dele (CASCADE). */
    public function destroy(PontoMonitoramento $ponto): JsonResponse
    {
        $ponto->delete();

        return $this->ok();
    }
}
