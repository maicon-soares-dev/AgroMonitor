<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelatorioGerencialRequest;
use App\Services\RelatorioGerencialService;
use Illuminate\Http\JsonResponse;

/** Tela "Relatórios gerenciais": agrupamentos e pivot. */
class RelatorioGerencialController extends Controller
{
    public function __construct(private RelatorioGerencialService $relatorios)
    {
    }

    /**
     * GET /api/relatorios-gerenciais/agrupamentos — por_praga, por_area e
     * hectares_por_cliente. Filtros: safra_id, plantio_id, cliente_id,
     * area_id, tipo, data_inicio, data_fim.
     */
    public function agrupamentos(RelatorioGerencialRequest $request): JsonResponse
    {
        return $this->dados($this->relatorios->agrupamentos($request->filtros()));
    }

    /**
     * GET /api/relatorios-gerenciais/pivot?safra_id=|plantio_id=&tipo=&medida=
     * — itens × meses da safra (tipo padrão: praga; medida: ocorrencias|quantidade).
     */
    public function pivot(RelatorioGerencialRequest $request): JsonResponse
    {
        return $this->dados($this->relatorios->pivot($request->filtros()));
    }
}
