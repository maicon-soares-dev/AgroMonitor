<?php

namespace App\Services;

use App\Models\MonitoramentoFoto;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard e relatórios do sistema (antigos /dashboard e /relatorios/*).
 * Consultas multitabela com totalização feitas no Query Builder.
 */
class RelatorioService
{
    public function __construct(private PlantioService $plantios)
    {
    }

    /**
     * KPIs, hectares por cliente, plantios por mês (período 3, 6, 12 meses ou
     * "todos"), top 5 pragas e as 5 últimas áreas.
     */
    public function dashboard(string $periodo): array
    {
        $areas = DB::table('areas')->selectRaw('COUNT(*) as total, COALESCE(SUM(hectares), 0) as hectares')->first();

        $porCliente = DB::table('areas as a')
            ->join('clientes as c', 'c.id', '=', 'a.cliente_id')
            ->select('c.nome as cliente')
            ->selectRaw('COALESCE(SUM(a.hectares), 0) as hectares, COUNT(*) as qtd')
            ->groupBy('c.id', 'c.nome')
            ->orderByDesc('hectares')
            ->get();

        $plantiosPorMes = DB::table('plantios')
            ->selectRaw("DATE_FORMAT(data_plantio, '%Y-%m') as mes, COUNT(*) as qtd")
            ->when($periodo !== 'todos', function (Builder $q) use ($periodo) {
                $meses = in_array($periodo, ['3', '6', '12'], true) ? (int) $periodo : 6;
                $q->where('data_plantio', '>=', now()->subMonthsNoOverflow($meses)->toDateString());
            })
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $topPragas = DB::table('monitoramento_itens')
            ->select('nome as tipo_praga')
            ->selectRaw('COUNT(*) as qtd')
            ->whereNotNull('nome')
            ->where('nome', '<>', '')
            ->groupBy('nome')
            ->orderByDesc('qtd')
            ->limit(5)
            ->get();

        $areasRecentes = DB::table('areas as a')
            ->join('clientes as c', 'c.id', '=', 'a.cliente_id')
            ->select('a.id', 'a.nome', 'c.nome as nome_cliente', 'a.hectares', 'a.criado_em')
            ->orderByDesc('a.criado_em')
            ->orderByDesc('a.id')
            ->limit(5)
            ->get();

        return [
            'totalAreas' => (int) $areas->total,
            'totalHectares' => (float) $areas->hectares,
            'totalPlantios' => DB::table('plantios')->count(),
            'totalOcorrencias' => DB::table('monitoramento_itens')->count(),
            'criticosCriticos' => DB::table('monitoramentos')->where('nivel_controle', 'critico')->count(),
            'porCliente' => $porCliente,
            'plantiosPorMes' => $plantiosPorMes,
            'topPragas' => $topPragas,
            'areasRecentes' => $areasRecentes,
        ];
    }

    /** Dias (plantio + data) com monitoramento, para escolher o relatório do dia. */
    public function dias(?string $areaId)
    {
        return DB::table('monitoramentos as m')
            ->join('plantios as pl', 'pl.id', '=', 'm.plantio_id')
            ->join('areas as a', 'a.id', '=', 'pl.area_id')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->join('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->join('safras as s', 's.id', '=', 'pl.safra_id')
            ->when($areaId, fn ($q) => $q->where('a.id', $areaId))
            ->select('pl.id as plantio_id', 'm.data_monitoramento as data')
            ->selectRaw('COUNT(*) as qtd_pontos')
            ->addSelect('a.id as area_id', 'a.nome as area_nome', 'cl.nome as cliente_nome',
                'c.nome as cultura_nome', 's.nome as safra_nome')
            // MariaDB exige no GROUP BY todas as colunas que não são agregadas
            ->groupBy('pl.id', 'm.data_monitoramento', 'a.id', 'a.nome', 'cl.nome', 'c.nome', 's.nome')
            ->orderByDesc('m.data_monitoramento')
            ->orderBy('a.nome')
            ->get();
    }

    /**
     * Relatório 1 — monitoramento do dia: média dos pontos por praga, régua
     * fenológica, detalhe por ponto e fotos favoritas. Null se não houver
     * monitoramento no plantio nessa data.
     */
    public function diario(int $plantioId, string $data): ?array
    {
        $cabecalho = $this->cabecalhoPlantio($plantioId)
            ->addSelect('a.geometria as area_geometria')
            ->first();
        $cultura = MonitoramentoService::culturaCalculo($cabecalho->cultura_nome);

        $mons = DB::table('monitoramentos as m')
            ->join('pontos_monitoramento as p', 'p.id', '=', 'm.ponto_id')
            ->where('m.plantio_id', $plantioId)
            ->where('m.data_monitoramento', $data)
            ->select('m.*', 'p.numero_ponto', 'p.latitude as ponto_lat', 'p.longitude as ponto_lng')
            ->orderBy('p.numero_ponto')
            ->get();
        if ($mons->isEmpty()) {
            return null;
        }

        $ids = $mons->pluck('id');
        $itens = DB::table('monitoramento_itens')->whereIn('monitoramento_id', $ids)->orderBy('id')->get();
        // Só as fotos que o agrônomo favoritou entram no relatório
        $fotosDb = MonitoramentoFoto::whereIn('monitoramento_id', $ids)->where('favorita', 1)->orderBy('id')->get();

        // Bases do cálculo — milho: soma das plantas avaliadas (ex.: 4 pontos × 20 = 80);
        // soja: metros / 2 de cada ponto somados (média por ponto de pano)
        $plantasAvaliadas = $mons->sum(fn ($m) => (int) $m->milho_plantas_avaliadas);
        $divisorSoja = $mons->sum(fn ($m) => ((float) ($m->soja_metros_lineares ?: 2)) / 2);

        // Consolida os itens de todos os pontos (mesmo tipo + nome = mesmo item)
        $ordemSev = ['leve' => 1, 'moderada' => 2, 'severa' => 3];
        $consolidado = [];
        $itensPorMon = [];
        foreach ($itens as $it) {
            $itensPorMon[$it->monitoramento_id][] = $it;
            $chave = $it->tipo.'|'.mb_strtolower(trim($it->nome));
            $consolidado[$chave] ??= ['tipo' => $it->tipo, 'nome' => trim($it->nome), 'total' => null,
                'pontos' => [], 'severidade' => null, 'locais' => []];
            $c = &$consolidado[$chave];
            if ($it->quantidade !== null) {
                $c['total'] = ($c['total'] ?? 0) + (int) $it->quantidade;
            }
            $c['pontos'][$it->monitoramento_id] = true;
            if ($it->severidade && ($ordemSev[$it->severidade] ?? 0) > ($ordemSev[$c['severidade']] ?? 0)) {
                $c['severidade'] = $it->severidade;
            }
            if (! empty($it->observacoes)) {
                $c['locais'][] = trim($it->observacoes);
            }
            unset($c);
        }

        $grupos = ['praga' => 'pragas', 'doenca' => 'doencas', 'planta_daninha' => 'daninhas', 'clima' => 'clima'];
        $resultado = ['pragas' => [], 'doencas' => [], 'daninhas' => [], 'clima' => []];
        foreach ($consolidado as $c) {
            $item = [
                'nome' => $c['nome'],
                'total' => $c['total'],
                'indice' => null,
                'unidade' => null,
                'nivel' => null,
                'pontos_com' => count($c['pontos']),
                'severidade' => $c['severidade'],
                'locais' => array_values(array_unique($c['locais'])),
            ];
            // Índice só para praga com contagem; sem contagem (ex.: ovos) o front mostra "X de Y pontos"
            if ($c['tipo'] === 'praga' && $c['total'] !== null) {
                [$item['indice'], $item['unidade'], $item['nivel']] =
                    MonitoramentoService::indicePraga($c['total'], $cultura, $plantasAvaliadas, $divisorSoja);
            }
            $resultado[$grupos[$c['tipo']] ?? 'pragas'][] = $item;
        }
        // Pragas com maior índice primeiro; as sem contagem vão para o fim
        usort($resultado['pragas'], fn ($a, $b) => ($b['indice'] ?? -1) <=> ($a['indice'] ?? -1));

        $pontos = $mons->map(fn ($m) => [
            'numero_ponto' => $m->numero_ponto,
            'latitude' => $m->ponto_lat,
            'longitude' => $m->ponto_lng,
            'estagio' => $m->estagio_fenologico,
            'plantas_avaliadas' => $m->milho_plantas_avaliadas,
            'metros_lineares' => $m->soja_metros_lineares,
            'observacoes' => $m->observacoes,
            'itens' => array_map(fn ($it) => [
                'tipo' => $it->tipo, 'nome' => $it->nome, 'quantidade' => $it->quantidade,
                'severidade' => $it->severidade, 'observacoes' => $it->observacoes,
            ], $itensPorMon[$m->id] ?? []),
        ]);

        $pontoPorMon = $mons->pluck('numero_ponto', 'id');
        $fotos = $fotosDb->map(fn (MonitoramentoFoto $f) => [
            'url' => $f->url,
            'numero_ponto' => $pontoPorMon[$f->monitoramento_id] ?? null,
            'descricao' => $f->descricao,
        ]);

        $cabecalho->data = $data;
        $cabecalho->qtd_pontos = $mons->count();
        $cabecalho->plantas_avaliadas = $cultura === 'milho' ? $plantasAvaliadas : null;

        return [
            'cabecalho' => $cabecalho,
            'estadios' => $this->estadios($mons),
            'pragas' => $resultado['pragas'],
            'doencas' => $resultado['doencas'],
            'daninhas' => $resultado['daninhas'],
            'clima' => $resultado['clima'],
            'pontos' => $pontos,
            'fotos' => $fotos,
            'talhoes' => $this->plantios->talhoesDoPlantio($plantioId),
        ];
    }

    /** Relatório 2 — resumo da safra de um plantio: índice das pragas por dia, ocorrências e aplicações. */
    public function safra(int $plantioId): array
    {
        $cabecalho = $this->cabecalhoPlantio($plantioId)
            ->leftJoin('adubos as ad', 'ad.id', '=', 'pl.adubo_id')
            ->leftJoin('colheitas as co', 'co.plantio_id', '=', 'pl.id')
            ->addSelect('pl.populacao_semente', 'pl.adubo_qtd_kg', 'pl.condicao_plantio', 'pl.condicao_solo',
                'pl.observacoes', 'a.geometria as area_geometria', 'ad.nome as adubo_nome', 'ad.npk as adubo_npk',
                'co.data_colheita', 'co.produtividade_sc_ha', 'co.umidade_pct', 'co.observacoes as colheita_obs')
            ->first();

        [$dias, $ocorrencias, $totalPontos] = $this->resumoMonitoramentosPlantio(
            $plantioId, MonitoramentoService::culturaCalculo($cabecalho->cultura_nome)
        );

        $aplicacoes = DB::table('aplicacoes')
            ->where('plantio_id', $plantioId)
            ->orderBy('data_aplicacao')
            ->get(['data_aplicacao', 'produto', 'tipo_produto', 'dose', 'unidade_dose', 'observacoes']);

        return [
            'cabecalho' => $cabecalho,
            'dias' => $dias,
            'ocorrencias' => $ocorrencias,
            'aplicacoes' => $aplicacoes,
            'talhoes' => $this->plantios->talhoesDoPlantio($plantioId),
            'totais' => ['dias' => count($dias), 'pontos' => $totalPontos, 'aplicacoes' => $aplicacoes->count()],
        ];
    }

    /** Relatório 3 — histórico comparativo de todas as safras/culturas de uma área. */
    public function comparativo(int $areaId): ?array
    {
        $area = DB::table('areas as a')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->where('a.id', $areaId)
            ->first(['a.id', 'a.nome as area_nome', 'a.hectares', 'a.alqueires', 'cl.nome as cliente_nome']);

        return $area ? ['area' => $area, 'plantios' => $this->plantiosComResumo('pl.area_id', $areaId)] : null;
    }

    /** Relatório 4 — consolidado de todas as áreas de um cliente. */
    public function cliente(int $clienteId): ?array
    {
        $cliente = DB::table('clientes')->where('id', $clienteId)->first(['id', 'nome']);
        if (! $cliente) {
            return null;
        }

        return [
            'cliente' => $cliente,
            'areas' => DB::table('areas')->where('cliente_id', $clienteId)->orderBy('nome')->get(['id', 'nome', 'hectares']),
            'plantios' => $this->plantiosComResumo('a.cliente_id', $clienteId),
        ];
    }

    /**
     * Resumo dos monitoramentos de um plantio (antigo resumoMonitoramentosPlantio):
     * índice das pragas por dia + consolidado de ocorrências do ciclo.
     *
     * @return array{0: array, 1: array, 2: int} [dias, ocorrências, total de pontos avaliados]
     */
    public function resumoMonitoramentosPlantio(int $plantioId, ?string $cultura): array
    {
        $mons = DB::table('monitoramentos as m')
            ->join('pontos_monitoramento as p', 'p.id', '=', 'm.ponto_id')
            ->where('m.plantio_id', $plantioId)
            ->select('m.*', 'p.numero_ponto')
            ->orderBy('m.data_monitoramento')
            ->orderBy('p.numero_ponto')
            ->get();
        $itensPorMon = DB::table('monitoramento_itens')
            ->whereIn('monitoramento_id', $mons->pluck('id'))
            ->get()
            ->groupBy('monitoramento_id');

        // Agrupa os pontos por dia
        $dias = [];
        foreach ($mons as $m) {
            $d = $m->data_monitoramento;
            $dias[$d] ??= ['pontos' => 0, 'plantas' => 0, 'divisor' => 0, 'estadios' => [], 'itens' => []];
            $dias[$d]['pontos']++;
            $dias[$d]['plantas'] += (int) $m->milho_plantas_avaliadas;
            $dias[$d]['divisor'] += ((float) ($m->soja_metros_lineares ?: 2)) / 2;
            $e = mb_strtoupper(trim($m->estagio_fenologico ?? ''));
            if ($e !== '' && ! in_array($e, $dias[$d]['estadios'], true)) {
                $dias[$d]['estadios'][] = $e;
            }
            foreach ($itensPorMon[$m->id] ?? [] as $it) {
                $chave = $it->tipo.'|'.mb_strtolower(trim($it->nome));
                $dias[$d]['itens'][$chave] ??= ['tipo' => $it->tipo, 'nome' => trim($it->nome), 'total' => null];
                if ($it->quantidade !== null) {
                    $dias[$d]['itens'][$chave]['total'] = ($dias[$d]['itens'][$chave]['total'] ?? 0) + (int) $it->quantidade;
                }
            }
        }

        // Índice de cada praga por dia + consolidado do ciclo
        $diasOut = [];
        $ocorrencias = [];
        foreach ($dias as $data => $dia) {
            $pragasDia = [];
            foreach ($dia['itens'] as $chave => $it) {
                [$indice, $unidade] = $it['tipo'] === 'praga' && $it['total'] > 0
                    ? MonitoramentoService::indicePraga($it['total'], $cultura, $dia['plantas'], $dia['divisor'])
                    : [null, null];
                if ($indice !== null) {
                    $pragasDia[$it['nome']] = $indice;
                }

                $ocorrencias[$chave] ??= ['tipo' => $it['tipo'], 'nome' => $it['nome'], 'dias_com' => 0,
                    'pico_indice' => null, 'pico_data' => null, 'unidade' => null,
                    'primeira_data' => $data, 'ultima_data' => $data];
                $o = &$ocorrencias[$chave];
                $o['dias_com']++;
                $o['ultima_data'] = $data;
                if ($indice !== null && ($o['pico_indice'] === null || $indice > $o['pico_indice'])) {
                    [$o['pico_indice'], $o['pico_data'], $o['unidade']] = [$indice, $data, $unidade];
                }
                unset($o);
            }
            $diasOut[] = ['data' => $data, 'qtd_pontos' => $dia['pontos'], 'estadios' => $dia['estadios'], 'pragas' => $pragasDia];
        }

        // Pragas primeiro, depois maior pico, depois mais dias com ocorrência
        $ocorrencias = array_values($ocorrencias);
        usort($ocorrencias, fn ($a, $b) => [$a['tipo'] !== 'praga', -($a['pico_indice'] ?? -1), -$a['dias_com']]
            <=> [$b['tipo'] !== 'praga', -($b['pico_indice'] ?? -1), -$b['dias_com']]);

        return [$diasOut, $ocorrencias, $mons->count()];
    }

    /**
     * Plantios com colheita, nº de aplicações e resumo das ocorrências
     * (antigo plantiosComResumo) — relatórios comparativo e do cliente.
     * $coluna: 'pl.area_id' ou 'a.cliente_id'.
     */
    public function plantiosComResumo(string $coluna, int $valor): array
    {
        $qtdAplicacoes = DB::table('aplicacoes as ap')->selectRaw('COUNT(*)')->whereColumn('ap.plantio_id', 'pl.id');

        $plantios = DB::table('plantios as pl')
            ->join('areas as a', 'a.id', '=', 'pl.area_id')
            ->join('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->join('safras as s', 's.id', '=', 'pl.safra_id')
            ->leftJoin('variedades as v', 'v.id', '=', 'pl.variedade_id')
            ->leftJoin('colheitas as co', 'co.plantio_id', '=', 'pl.id')
            ->where($coluna, $valor)
            ->select('pl.id as plantio_id', 'pl.data_plantio', 'pl.area_id', 'a.nome as area_nome', 'a.hectares',
                'c.nome as cultura_nome', 's.nome as safra_nome', 's.ano_inicio', 's.mes_inicio')
            ->selectRaw(PlantioService::sqlVariedadeNome('pl', 'v').' as variedade_nome')
            ->addSelect('co.data_colheita', 'co.produtividade_sc_ha', 'co.umidade_pct')
            ->selectSub($qtdAplicacoes, 'qtd_aplicacoes')
            ->orderBy('pl.data_plantio')
            ->orderBy('a.nome')
            ->get();

        return $plantios->map(function ($p) {
            $cultura = MonitoramentoService::culturaCalculo($p->cultura_nome);
            [$dias, $ocorrencias, $totalPontos] = $this->resumoMonitoramentosPlantio($p->plantio_id, $cultura);
            $doTipo = fn ($tipo) => count(array_filter($ocorrencias, fn ($o) => $o['tipo'] === $tipo));

            return (array) $p + [
                'dias_monitorados' => count($dias),
                'pontos_avaliados' => $totalPontos,
                'qtd_pragas' => $doTipo('praga'),
                'qtd_doencas' => $doTipo('doenca'),
                'qtd_daninhas' => $doTipo('planta_daninha'),
                // Pragas que passaram do nível de controle em algum dia
                'pragas_criticas' => count(array_filter($ocorrencias, fn ($o) => $o['pico_indice'] !== null
                    && (($cultura === 'milho' && $o['pico_indice'] > MonitoramentoService::LIMITE_MILHO)
                        || ($cultura === 'soja' && $o['pico_indice'] >= MonitoramentoService::LIMITE_SOJA)))),
                // As 3 principais pragas (maior pico)
                'principais_pragas' => array_slice(array_values(array_filter($ocorrencias,
                    fn ($o) => $o['tipo'] === 'praga' && $o['pico_indice'] !== null)), 0, 3),
                // Tudo o que apareceu no ciclo — usado no consolidado do cliente
                'ocorrencias' => array_map(fn ($o) => [
                    'tipo' => $o['tipo'], 'nome' => $o['nome'], 'pico_indice' => $o['pico_indice'], 'unidade' => $o['unidade'],
                ], $ocorrencias),
            ];
        })->all();
    }

    /** Cabeçalho comum dos relatórios de plantio: cliente, área, cultura, safra e variedade(s). */
    private function cabecalhoPlantio(int $plantioId): Builder
    {
        return DB::table('plantios as pl')
            ->join('areas as a', 'a.id', '=', 'pl.area_id')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->join('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->join('safras as s', 's.id', '=', 'pl.safra_id')
            ->leftJoin('variedades as v', 'v.id', '=', 'pl.variedade_id')
            ->where('pl.id', $plantioId)
            ->select('pl.id as plantio_id', 'pl.data_plantio', 'a.nome as area_nome', 'a.hectares', 'a.alqueires',
                'cl.nome as cliente_nome', 'c.nome as cultura_nome', 's.nome as safra_nome')
            ->selectRaw(PlantioService::sqlVariedadeNome('pl', 'v').' as variedade_nome');
    }

    /** Estádio(s) do dia; se os pontos divergirem vira faixa (ex.: R2/R3). */
    private function estadios($mons): array
    {
        return $mons->map(fn ($m) => mb_strtoupper(trim($m->estagio_fenologico ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
