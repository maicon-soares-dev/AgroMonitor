<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ColheitaRequest;
use App\Models\Colheita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Colheitas: uma por plantio, com produtividade (sc/ha) e umidade. */
class ColheitaController extends Controller
{
    /**
     * GET /api/colheitas — da mais recente para a mais antiga.
     * Filtros: area_id, safra_id, cultura_id, cliente_id,
     * busca (área, cliente ou variedade).
     */
    public function index(Request $request): JsonResponse
    {
        $colheitas = Colheita::comResumo()
            ->when($request->filled('area_id'), fn ($q) => $q->where('pl.area_id', $request->area_id))
            ->when($request->filled('safra_id'), fn ($q) => $q->where('pl.safra_id', $request->safra_id))
            ->when($request->filled('cultura_id'), fn ($q) => $q->where('pl.cultura_id', $request->cultura_id))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('a.cliente_id', $request->cliente_id))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('a.nome', 'like', $busca)
                    ->orWhere('cl.nome', 'like', $busca)
                    ->orWhere('v.nome', 'like', $busca)
                    ->orWhereExists(fn ($e) => $e->selectRaw('1')
                        ->from('plantio_talhoes as pt')
                        ->join('variedades as vt', 'vt.id', '=', 'pt.variedade_id')
                        ->whereColumn('pt.plantio_id', 'pl.id')
                        ->where('vt.nome', 'like', $busca)));
            })
            ->orderByDesc('colheitas.data_colheita')
            ->orderByDesc('colheitas.id')
            ->get();

        return $this->dados($colheitas);
    }

    public function show(Colheita $colheita): JsonResponse
    {
        return $this->dados(Colheita::comResumo()->where('colheitas.id', $colheita->id)->first());
    }

    public function store(ColheitaRequest $request): JsonResponse
    {
        $colheita = Colheita::create($request->validated());

        return $this->criado($colheita->id);
    }

    public function update(ColheitaRequest $request, Colheita $colheita): JsonResponse
    {
        $colheita->update($request->validated());

        return $this->ok();
    }

    public function destroy(Colheita $colheita): JsonResponse
    {
        $colheita->delete();

        return $this->ok();
    }
}
