<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plantio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/resumo-monitoramento[?area_id=] — tela de Monitoramento: cada
 * plantio (agrupado por área e safra no front) com a quantidade de
 * monitoramentos, críticos, em atenção e a data do último.
 */
class ResumoMonitoramentoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $resumo = Plantio::query()
            ->join('areas as a', 'a.id', '=', 'plantios.area_id')
            ->join('safras as s', 's.id', '=', 'plantios.safra_id')
            ->join('culturas as c', 'c.id', '=', 'plantios.cultura_id')
            ->leftJoin('monitoramentos as m', 'm.plantio_id', '=', 'plantios.id')
            ->when($request->filled('area_id'), fn ($q) => $q->where('plantios.area_id', $request->area_id))
            ->select('a.id as area_id', 'a.nome as area_nome', 's.id as safra_id', 's.nome as safra_nome',
                'plantios.id as plantio_id', 'plantios.data_plantio', 'c.nome as cultura_nome')
            ->selectRaw('COUNT(m.id) as qtd_monitoramentos')
            ->selectRaw("SUM(CASE WHEN m.nivel_controle = 'critico' THEN 1 ELSE 0 END) as qtd_criticos")
            ->selectRaw("SUM(CASE WHEN m.nivel_controle = 'atencao' THEN 1 ELSE 0 END) as qtd_atencao")
            ->selectRaw('MAX(m.data_monitoramento) as ultimo_monitoramento')
            // MariaDB exige no GROUP BY todas as colunas que não são agregadas
            ->groupBy('plantios.id', 'plantios.data_plantio', 'a.id', 'a.nome', 's.id', 's.nome',
                's.ano_inicio', 's.mes_inicio', 'c.nome')
            ->orderBy('a.nome')
            ->orderByDesc('s.ano_inicio')
            ->orderByDesc('s.mes_inicio')
            ->orderByDesc('plantios.data_plantio')
            ->toBase()
            ->get();

        return $this->dados($resumo);
    }
}
