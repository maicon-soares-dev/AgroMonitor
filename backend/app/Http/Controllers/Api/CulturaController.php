<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CulturaRequest;
use App\Models\Cultura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CulturaController extends Controller
{
    /** GET /api/culturas — só ativas. Filtro: busca (nome ou descrição). */
    public function index(Request $request): JsonResponse
    {
        $culturas = Cultura::where('ativa', 1)
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $busca)->orWhere('descricao', 'like', $busca));
            })
            ->orderBy('nome')
            ->get();

        return $this->dados($culturas);
    }

    public function show(Cultura $cultura): JsonResponse
    {
        return $this->dados($cultura);
    }

    public function store(CulturaRequest $request): JsonResponse
    {
        $cultura = Cultura::create($request->validated());

        return $this->criado($cultura->id);
    }

    public function update(CulturaRequest $request, Cultura $cultura): JsonResponse
    {
        $cultura->update($request->validated());

        return $this->ok();
    }

    /** Apaga junto as variedades (CASCADE); com plantio vinculado, o handler devolve 409. */
    public function destroy(Cultura $cultura): JsonResponse
    {
        $cultura->delete();

        return $this->ok();
    }
}
