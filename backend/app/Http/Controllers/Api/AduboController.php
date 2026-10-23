<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AduboRequest;
use App\Models\Adubo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AduboController extends Controller
{
    /** GET /api/adubos — só ativos. Filtro: busca (nome, tipo ou NPK). */
    public function index(Request $request): JsonResponse
    {
        $adubos = Adubo::where('ativa', 1)
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $busca)
                    ->orWhere('tipo', 'like', $busca)
                    ->orWhere('npk', 'like', $busca));
            })
            ->orderBy('nome')
            ->get();

        return $this->dados($adubos);
    }

    public function show(Adubo $adubo): JsonResponse
    {
        return $this->dados($adubo);
    }

    public function store(AduboRequest $request): JsonResponse
    {
        $adubo = Adubo::create($request->validated());

        return $this->criado($adubo->id);
    }

    public function update(AduboRequest $request, Adubo $adubo): JsonResponse
    {
        $adubo->update($request->validated());

        return $this->ok();
    }

    /** Plantios com este adubo ficam sem adubo (SET NULL). */
    public function destroy(Adubo $adubo): JsonResponse
    {
        $adubo->delete();

        return $this->ok();
    }
}
