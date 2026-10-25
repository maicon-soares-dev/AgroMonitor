<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plantio;
use App\Services\RelatorioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Relatórios do sistema: do dia, da safra, comparativo da área e do cliente. */
class RelatorioController extends Controller
{
    public function __construct(private RelatorioService $relatorios)
    {
    }

    /** GET /api/relatorios/dias[?area_id=] — dias com monitoramento, para escolher o relatório. */
    public function dias(Request $request): JsonResponse
    {
        $request->validate(['area_id' => ['nullable', 'integer']]);

        return $this->dados($this->relatorios->dias($request->query('area_id')));
    }

    /** GET /api/relatorios/diario?plantio_id=&data= — Relatório 1 (monitoramento do dia). */
    public function diario(Request $request): JsonResponse
    {
        $request->validate(
            ['plantio_id' => ['required', 'integer'], 'data' => ['required', 'date_format:Y-m-d']],
            [],
            ['plantio_id' => 'plantio', 'data' => 'data']
        );
        if (! Plantio::whereKey($request->plantio_id)->exists()) {
            return $this->erro('Plantio não encontrado', 404);
        }

        $relatorio = $this->relatorios->diario((int) $request->plantio_id, $request->data);

        return $relatorio ? $this->dados($relatorio) : $this->erro('Nenhum monitoramento nesse plantio e data', 404);
    }

    /** GET /api/relatorios/safra?plantio_id= — Relatório 2 (resumo da safra do plantio). */
    public function safra(Request $request): JsonResponse
    {
        $request->validate(['plantio_id' => ['required', 'integer']], [], ['plantio_id' => 'plantio']);
        if (! Plantio::whereKey($request->plantio_id)->exists()) {
            return $this->erro('Plantio não encontrado', 404);
        }

        return $this->dados($this->relatorios->safra((int) $request->plantio_id));
    }

    /** GET /api/relatorios/comparativo?area_id= — Relatório 3 (histórico comparativo da área). */
    public function comparativo(Request $request): JsonResponse
    {
        $request->validate(['area_id' => ['required', 'integer']], [], ['area_id' => 'área']);
        $relatorio = $this->relatorios->comparativo((int) $request->area_id);

        return $relatorio ? $this->dados($relatorio) : $this->erro('Área não encontrada', 404);
    }

    /** GET /api/relatorios/cliente?cliente_id= — Relatório 4 (consolidado do cliente). */
    public function cliente(Request $request): JsonResponse
    {
        $request->validate(['cliente_id' => ['required', 'integer']], [], ['cliente_id' => 'cliente']);
        $relatorio = $this->relatorios->cliente((int) $request->cliente_id);

        return $relatorio ? $this->dados($relatorio) : $this->erro('Cliente não encontrado', 404);
    }
}
