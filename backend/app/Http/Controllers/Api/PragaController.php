<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PragaRequest;
use App\Models\Praga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PragaController extends Controller
{
    /**
     * GET /api/pragas — só ativas, com o nome da cultura (cultura_nome).
     * Filtros: cultura_id (traz também os itens de "qualquer cultura"),
     * tipo, nivel_risco, busca (nome ou descrição).
     */
    public function index(Request $request): JsonResponse
    {
        $pragas = Praga::query()
            ->leftJoin('culturas', 'culturas.id', '=', 'pragas.cultura_id')
            ->select('pragas.*', 'culturas.nome as cultura_nome')
            ->where('pragas.ativa', 1)
            ->when($request->filled('cultura_id'), fn ($q) => $q->where(fn ($w) => $w
                ->where('pragas.cultura_id', $request->cultura_id)
                ->orWhereNull('pragas.cultura_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('pragas.tipo', $request->tipo))
            ->when($request->filled('nivel_risco'), fn ($q) => $q->where('pragas.nivel_risco', $request->nivel_risco))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('pragas.nome', 'like', $busca)->orWhere('pragas.descricao', 'like', $busca));
            })
            ->orderBy('pragas.nome')
            ->get();

        return $this->dados($pragas);
    }

    public function show(Praga $praga): JsonResponse
    {
        return $this->dados($praga);
    }

    public function store(PragaRequest $request): JsonResponse
    {
        $praga = Praga::create($this->campos($request));

        return $this->criado($praga->id);
    }

    public function update(PragaRequest $request, Praga $praga): JsonResponse
    {
        $praga->update($this->campos($request));

        return $this->ok();
    }

    public function destroy(Praga $praga): JsonResponse
    {
        $praga->delete();

        return $this->ok();
    }

    /** Tipo vazio vira "praga" e risco vazio vira "medio", como na API antiga. */
    private function campos(PragaRequest $request): array
    {
        return [
            'nome' => $request->nome,
            'tipo' => $request->tipo ?: 'praga',
            'cultura_id' => $request->cultura_id,
            'nivel_risco' => $request->nivel_risco ?: 'medio',
            'descricao' => $request->descricao,
        ];
    }
}
