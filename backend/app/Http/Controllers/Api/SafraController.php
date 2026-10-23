<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SafraRequest;
use App\Models\Safra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafraController extends Controller
{
    /**
     * GET /api/safras — todas, da mais recente para a mais antiga.
     * Filtros: busca (nome), ano (safras que passam por esse ano), ativa (1 | 0).
     */
    public function index(Request $request): JsonResponse
    {
        $safras = Safra::query()
            ->when($request->filled('busca'), fn ($q) => $q->where('nome', 'like', '%'.$request->busca.'%'))
            ->when($request->filled('ano'), fn ($q) => $q->where('ano_inicio', '<=', (int) $request->ano)
                ->where('ano_fim', '>=', (int) $request->ano))
            ->when($request->filled('ativa'), fn ($q) => $q->where('ativa', $request->boolean('ativa')))
            ->orderByDesc('ano_inicio')
            ->orderByDesc('mes_inicio')
            ->orderBy('nome')
            ->get();

        return $this->dados($safras);
    }

    public function show(Safra $safra): JsonResponse
    {
        return $this->dados($safra);
    }

    public function store(SafraRequest $request): JsonResponse
    {
        $safra = Safra::create($this->campos($request));

        return $this->criado($safra->id);
    }

    public function update(SafraRequest $request, Safra $safra): JsonResponse
    {
        $safra->update($this->campos($request));

        return $this->ok();
    }

    /** Safra com plantio não pode ser excluída (FK RESTRICT) — o handler devolve 409. */
    public function destroy(Safra $safra): JsonResponse
    {
        $safra->delete();

        return $this->ok();
    }

    /** Mês vazio vira janeiro/dezembro e "ativa" vazia vira 1, como na API antiga. */
    private function campos(SafraRequest $request): array
    {
        return [
            'nome' => $request->nome,
            'mes_inicio' => $request->mes_inicio ?? 1,
            'ano_inicio' => $request->ano_inicio,
            'mes_fim' => $request->mes_fim ?? 12,
            'ano_fim' => $request->ano_fim,
            'ativa' => $request->has('ativa') ? (int) $request->boolean('ativa') : 1,
        ];
    }
}
