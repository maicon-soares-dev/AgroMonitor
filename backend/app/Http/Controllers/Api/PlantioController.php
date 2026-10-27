<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlantioRequest;
use App\Models\Plantio;
use App\Services\PlantioService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlantioController extends Controller
{
    public function __construct(private PlantioService $plantios)
    {
    }

    /**
     * GET /api/plantios — do mais recente para o mais antigo, com os nomes
     * relacionados e a lista "talhoes" (talhao_id + variedade_id).
     * Filtros: area_id, safra_id, cultura_id, busca (área, cultura ou variedade).
     */
    public function index(Request $request): JsonResponse
    {
        $plantios = Plantio::comResumo()
            ->when($request->filled('area_id'), fn ($q) => $q->where('plantios.area_id', $request->area_id))
            ->when($request->filled('safra_id'), fn ($q) => $q->where('plantios.safra_id', $request->safra_id))
            ->when($request->filled('cultura_id'), fn ($q) => $q->where('plantios.cultura_id', $request->cultura_id))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('areas.nome', 'like', $busca)
                    ->orWhere('culturas.nome', 'like', $busca)
                    ->orWhere('variedades.nome', 'like', $busca)
                    ->orWhereHas('plantioTalhoes.variedade', fn ($v) => $v->where('nome', 'like', $busca)));
            })
            ->orderByDesc('plantios.data_plantio')
            ->orderByDesc('plantios.id')
            ->get();

        return $this->dados($this->comTalhoes($plantios));
    }

    public function show(Plantio $plantio): JsonResponse
    {
        $plantios = Plantio::comResumo()->where('plantios.id', $plantio->id)->get();

        return $this->dados($this->comTalhoes($plantios)[0]);
    }

    public function store(PlantioRequest $request): JsonResponse
    {
        $plantio = $this->plantios->criar($request->validated());

        return $this->criado($plantio->id);
    }

    public function update(PlantioRequest $request, Plantio $plantio): JsonResponse
    {
        $this->plantios->atualizar($plantio, $request->validated());

        return $this->ok();
    }

    /** Apaga junto monitoramentos, colheita e variedades por talhão (CASCADE). */
    public function destroy(Plantio $plantio): JsonResponse
    {
        $plantio->delete();

        return $this->ok();
    }

    /** Acrescenta "talhoes": [{plantio_id, talhao_id, variedade_id}] em cada plantio. */
    private function comTalhoes(Collection $plantios): array
    {
        $plantios->load('plantioTalhoes:id,plantio_id,talhao_id,variedade_id');

        return $plantios->map(fn (Plantio $p) => $p->attributesToArray() + [
            'talhoes' => $p->plantioTalhoes->map->only(['plantio_id', 'talhao_id', 'variedade_id'])->values(),
        ])->all();
    }
}
