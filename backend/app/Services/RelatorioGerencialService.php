<?php

namespace App\Services;

use App\Models\Plantio;
use App\Models\Safra;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios gerenciais: consultas de agrupamento (ocorrências por praga e
 * por área, hectares por cliente) e o pivot pragas × meses.
 * Tudo no Query Builder (GROUP BY + COUNT/SUM/AVG e SUM(CASE WHEN ...)).
 */
class RelatorioGerencialService
{
    const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    /**
     * As três consultas de agrupamento com os mesmos filtros:
     * safra_id, plantio_id, cliente_id, area_id, tipo, data_inicio, data_fim.
     */
    public function agrupamentos(array $filtros): array
    {
        return [
            'por_praga' => $this->ocorrenciasPorPraga($filtros),
            'por_area' => $this->ocorrenciasPorArea($filtros),
            'hectares_por_cliente' => $this->hectaresPorCliente($filtros),
        ];
    }

    /** Ocorrências agrupadas por item (tipo + nome): quantas vezes, em quantos monitoramentos e áreas. */
    public function ocorrenciasPorPraga(array $filtros)
    {
        $consulta = DB::table('monitoramento_itens as i')
            ->join('monitoramentos as m', 'm.id', '=', 'i.monitoramento_id')
            ->join('pontos_monitoramento as p', 'p.id', '=', 'm.ponto_id')
            ->join('plantios as pl', 'pl.id', '=', 'm.plantio_id')
            ->join('areas as a', 'a.id', '=', 'p.area_id')
            ->select('i.tipo', 'i.nome')
            ->selectRaw('COUNT(*) as ocorrencias')
            ->selectRaw('COUNT(DISTINCT m.id) as monitoramentos')
            ->selectRaw('COUNT(DISTINCT p.area_id) as areas')
            ->selectRaw('SUM(i.quantidade) as quantidade_total')
            ->selectRaw('ROUND(AVG(i.quantidade), 1) as media_quantidade')
            ->selectRaw('MIN(m.data_monitoramento) as primeira_data')
            ->selectRaw('MAX(m.data_monitoramento) as ultima_data')
            ->when(($filtros['tipo'] ?? 'todos') !== 'todos', fn ($q) => $q->where('i.tipo', $filtros['tipo']))
            ->groupBy('i.tipo', 'i.nome')
            ->orderByDesc('ocorrencias')
            ->orderBy('i.nome');

        return $this->filtrar($consulta, $filtros)->get();
    }

    /** Monitoramentos, ocorrências e pontos críticos agrupados por área. */
    public function ocorrenciasPorArea(array $filtros)
    {
        $tipo = $filtros['tipo'] ?? 'todos';

        $consulta = DB::table('monitoramentos as m')
            ->join('pontos_monitoramento as p', 'p.id', '=', 'm.ponto_id')
            ->join('plantios as pl', 'pl.id', '=', 'm.plantio_id')
            ->join('areas as a', 'a.id', '=', 'p.area_id')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            // O filtro de tipo vai no JOIN para a área continuar aparecendo sem ocorrência daquele tipo
            ->leftJoin('monitoramento_itens as i', function ($j) use ($tipo) {
                $j->on('i.monitoramento_id', '=', 'm.id');
                if ($tipo !== 'todos') {
                    $j->where('i.tipo', '=', $tipo);
                }
            })
            ->select('a.id as area_id', 'a.nome as area_nome', 'cl.nome as cliente_nome', 'a.hectares')
            ->selectRaw('COUNT(DISTINCT m.id) as monitoramentos')
            ->selectRaw('COUNT(DISTINCT m.data_monitoramento) as dias_monitorados')
            ->selectRaw('COUNT(i.id) as ocorrencias')
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.nivel_controle = 'critico' THEN m.id END) as criticos")
            ->groupBy('a.id', 'a.nome', 'cl.nome', 'a.hectares')
            ->orderByDesc('ocorrencias')
            ->orderBy('a.nome');

        return $this->filtrar($consulta, $filtros)->get();
    }

    /**
     * Áreas e hectares por cliente (soma, média e alqueires). Com safra ou
     * plantio no filtro, só entram as áreas plantadas nele.
     */
    public function hectaresPorCliente(array $filtros)
    {
        return DB::table('areas as a')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->select('cl.id as cliente_id', 'cl.nome as cliente_nome')
            ->selectRaw('COUNT(*) as qtd_areas')
            ->selectRaw('SUM(a.hectares) as hectares')
            ->selectRaw('SUM(a.alqueires) as alqueires')
            ->selectRaw('ROUND(AVG(a.hectares), 2) as media_hectares')
            ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('a.cliente_id', $id))
            ->when($filtros['area_id'] ?? null, fn ($q, $id) => $q->where('a.id', $id))
            ->when(isset($filtros['safra_id']) || isset($filtros['plantio_id']), fn ($q) => $q->whereExists(fn ($e) => $e
                ->selectRaw('1')
                ->from('plantios as pl')
                ->whereColumn('pl.area_id', 'a.id')
                ->when($filtros['safra_id'] ?? null, fn ($w, $id) => $w->where('pl.safra_id', $id))
                ->when($filtros['plantio_id'] ?? null, fn ($w, $id) => $w->where('pl.id', $id))))
            ->groupBy('cl.id', 'cl.nome')
            ->orderByDesc('hectares')
            ->get();
    }

    /**
     * Pivot: itens (linhas) × meses da safra (colunas), com total por item e
     * por mês. Cada coluna é um SUM(CASE WHEN data no mês THEN valor ELSE 0 END).
     * medida "ocorrencias" conta os registros; "quantidade" soma as quantidades.
     */
    public function pivot(array $filtros): array
    {
        $safra = isset($filtros['plantio_id'])
            ? Plantio::findOrFail($filtros['plantio_id'])->safra
            : Safra::findOrFail($filtros['safra_id']);
        $meses = $this->mesesDaSafra($safra);
        $valor = ($filtros['medida'] ?? 'ocorrencias') === 'quantidade' ? 'COALESCE(i.quantidade, 0)' : '1';

        $consulta = DB::table('monitoramento_itens as i')
            ->join('monitoramentos as m', 'm.id', '=', 'i.monitoramento_id')
            ->join('plantios as pl', 'pl.id', '=', 'm.plantio_id')
            ->select('i.tipo', 'i.nome')
            ->whereBetween('m.data_monitoramento', [$meses[0]['inicio'], end($meses)['fim']])
            ->when($filtros['plantio_id'] ?? null,
                fn ($q, $id) => $q->where('m.plantio_id', $id),
                fn ($q) => $q->where('pl.safra_id', $safra->id))
            ->when(($filtros['tipo'] ?? 'praga') !== 'todos', fn ($q) => $q->where('i.tipo', $filtros['tipo'] ?? 'praga'));

        foreach ($meses as $mes) {
            $consulta->selectRaw(
                "SUM(CASE WHEN m.data_monitoramento BETWEEN ? AND ? THEN {$valor} ELSE 0 END) as {$mes['alias']}",
                [$mes['inicio'], $mes['fim']]
            );
        }
        $linhas = $consulta->selectRaw("SUM({$valor}) as total")
            ->groupBy('i.tipo', 'i.nome')
            ->orderByDesc('total')
            ->orderBy('i.nome')
            ->get();

        // Reorganiza cada linha em {tipo, nome, meses: {"2026-10": n}, total} e soma a linha de totais
        $totaisMes = array_fill_keys(array_column($meses, 'chave'), 0);
        $linhas = $linhas->map(function ($l) use ($meses, &$totaisMes) {
            $valores = [];
            foreach ($meses as $mes) {
                $valores[$mes['chave']] = (int) $l->{$mes['alias']};
                $totaisMes[$mes['chave']] += $valores[$mes['chave']];
            }

            return ['tipo' => $l->tipo, 'nome' => $l->nome, 'meses' => $valores, 'total' => (int) $l->total];
        });

        return [
            'safra' => ['id' => $safra->id, 'nome' => $safra->nome],
            'colunas' => array_map(fn ($m) => ['chave' => $m['chave'], 'rotulo' => $m['rotulo']], $meses),
            'linhas' => $linhas,
            'totais' => ['meses' => $totaisMes, 'total' => array_sum($totaisMes)],
        ];
    }

    /** Filtros comuns das consultas sobre monitoramentos (aliases m, p, pl, a). */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when($filtros['safra_id'] ?? null, fn ($q, $id) => $q->where('pl.safra_id', $id))
            ->when($filtros['plantio_id'] ?? null, fn ($q, $id) => $q->where('m.plantio_id', $id))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('a.cliente_id', $id))
            ->when($filtros['area_id'] ?? null, fn ($q, $id) => $q->where('p.area_id', $id))
            ->when($filtros['data_inicio'] ?? null, fn ($q, $d) => $q->where('m.data_monitoramento', '>=', $d))
            ->when($filtros['data_fim'] ?? null, fn ($q, $d) => $q->where('m.data_monitoramento', '<=', $d));
    }

    /** Meses de mes_inicio/ano_inicio até mes_fim/ano_fim da safra (máx. 24). */
    private function mesesDaSafra(Safra $safra): array
    {
        $mes = CarbonImmutable::create($safra->ano_inicio, $safra->mes_inicio ?: 1, 1);
        $fim = CarbonImmutable::create($safra->ano_fim, $safra->mes_fim ?: 12, 1);
        $meses = [];
        while ($mes <= $fim && count($meses) < 24) {
            $meses[] = [
                'chave' => $mes->format('Y-m'),
                'alias' => 'm_'.$mes->format('Y_m'),
                'rotulo' => self::MESES[$mes->month - 1].'/'.$mes->format('y'),
                'inicio' => $mes->toDateString(),
                'fim' => $mes->endOfMonth()->toDateString(),
            ];
            $mes = $mes->addMonth();
        }

        return $meses;
    }
}
