<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaRequest;
use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function __construct(private AreaService $areas)
    {
    }

    /**
     * GET /api/areas — mais recentes primeiro, com nome_cliente e qtd_talhoes.
     * Filtros: safra_id (áreas com plantio na safra), cliente_id,
     * busca (nome da área ou do cliente).
     */
    public function index(Request $request): JsonResponse
    {
        $areas = Area::comResumo()
            ->when($request->filled('safra_id'), fn ($q) => $q->whereHas('plantios',
                fn ($p) => $p->where('safra_id', $request->safra_id)))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('areas.cliente_id', $request->cliente_id))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('areas.nome', 'like', $busca)->orWhere('clientes.nome', 'like', $busca));
            })
            ->orderByDesc('areas.criado_em')
            ->orderByDesc('areas.id')
            ->get();

        return $this->dados($areas);
    }

    public function show(Area $area): JsonResponse
    {
        return $this->dados(Area::comResumo()->where('areas.id', $area->id)->first());
    }

    /** Cria a área com os pontos de monitoramento e os talhões (AreaService). */
    public function store(AreaRequest $request): JsonResponse
    {
        $area = $this->areas->criar($request->validated());

        return $this->criado($area->id);
    }

    public function update(AreaRequest $request, Area $area): JsonResponse
    {
        $this->areas->atualizar($area, $request->validated());

        return $this->ok();
    }

    /** Apaga junto talhões, pontos, plantios e monitoramentos (CASCADE). */
    public function destroy(Area $area): JsonResponse
    {
        $area->delete();

        return $this->ok();
    }
}
