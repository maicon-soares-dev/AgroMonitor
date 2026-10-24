<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TalhaoRequest;
use App\Models\Talhao;
use App\Services\TalhaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Talhão = divisão física fixa da área (nome, cor, contorno). A variedade
 * muda a cada safra e fica em plantio_talhoes.
 */
class TalhaoController extends Controller
{
    public function __construct(private TalhaoService $talhoes)
    {
    }

    /**
     * GET /api/talhoes?area_id=&plantio_id=
     * Com plantio_id, cada talhão vem com a variedade naquele plantio
     * (plantio_variedade_id, plantio_variedade_nome) e no_plantio (1 | 0).
     */
    public function index(Request $request): JsonResponse
    {
        $plantioId = $request->input('plantio_id') ?: 0;

        $talhoes = Talhao::query()
            ->leftJoin('plantio_talhoes as pt', fn ($j) => $j->on('pt.talhao_id', '=', 'talhoes.id')
                ->where('pt.plantio_id', '=', $plantioId))
            ->leftJoin('variedades as v', 'v.id', '=', 'pt.variedade_id')
            ->select('talhoes.*', 'pt.variedade_id as plantio_variedade_id', 'v.nome as plantio_variedade_nome')
            ->selectRaw('(pt.id IS NOT NULL) as no_plantio')
            ->when($request->filled('area_id'), fn ($q) => $q->where('talhoes.area_id', $request->area_id))
            ->orderBy('talhoes.nome')
            ->orderBy('talhoes.id')
            ->get();

        return $this->dados($talhoes);
    }

    public function show(Talhao $talhao): JsonResponse
    {
        return $this->dados($talhao);
    }

    public function store(TalhaoRequest $request): JsonResponse
    {
        $talhao = $this->talhoes->inserir((int) $request->area_id, $request->validated());

        return $this->criado($talhao->id);
    }

    /** Atualiza só o que veio no corpo (nome, cor, contorno/hectares). */
    public function update(TalhaoRequest $request, Talhao $talhao): JsonResponse
    {
        $campos = $request->safe()->only(['nome', 'cor_hex', 'hectares', 'geometria']);
        if (! $campos) {
            return $this->erro('Nada para atualizar');
        }

        if (array_key_exists('nome', $campos)) {
            $campos['nome'] = trim($campos['nome']);
        }
        if (array_key_exists('cor_hex', $campos)) {
            $campos['cor_hex'] = $campos['cor_hex'] ?: '#4caf50';
        }
        if (array_key_exists('geometria', $campos)) {
            $campos['geometria'] = $campos['geometria'] !== null ? json_encode($campos['geometria']) : null;
        }
        $talhao->update($campos);

        return $this->ok();
    }

    /** plantio_talhoes cai junto (CASCADE); pontos do talhão ficam sem talhão (SET NULL). */
    public function destroy(Talhao $talhao): JsonResponse
    {
        $talhao->delete();

        return $this->ok();
    }
}
