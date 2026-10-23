<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VariedadeRequest;
use App\Models\Variedade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariedadeController extends Controller
{
    /** GET /api/variedades — só ativas. Filtros: cultura_id, busca (nome ou descrição). */
    public function index(Request $request): JsonResponse
    {
        $variedades = Variedade::where('ativa', 1)
            ->when($request->filled('cultura_id'), fn ($q) => $q->where('cultura_id', $request->cultura_id))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $busca)->orWhere('descricao', 'like', $busca));
            })
            ->orderBy('nome')
            ->get();

        return $this->dados($variedades);
    }

    public function show(Variedade $variedade): JsonResponse
    {
        return $this->dados($variedade);
    }

    public function store(VariedadeRequest $request): JsonResponse
    {
        $variedade = Variedade::create($request->validated());

        return $this->criado($variedade->id);
    }

    public function update(VariedadeRequest $request, Variedade $variedade): JsonResponse
    {
        $variedade->update($request->validated());

        return $this->ok();
    }

    /** Plantios com esta variedade ficam sem variedade (SET NULL). */
    public function destroy(Variedade $variedade): JsonResponse
    {
        $variedade->delete();

        return $this->ok();
    }
}
