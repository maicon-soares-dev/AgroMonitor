<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AplicacaoRequest;
use App\Models\Aplicacao;
use App\Models\Plantio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Aplicações de defensivo, adubo ou corretivo (movimento ligado ao plantio). */
class AplicacaoController extends Controller
{
    /**
     * GET /api/aplicacoes — da mais recente para a mais antiga.
     * Filtros: area_id, plantio_id, safra_id, cliente_id, tipo_produto,
     * data_inicio, data_fim, busca (produto, área, cliente ou observações).
     */
    public function index(Request $request): JsonResponse
    {
        $aplicacoes = Aplicacao::comResumo()
            ->when($request->filled('area_id'), fn ($q) => $q->where('aplicacoes.area_id', $request->area_id))
            ->when($request->filled('plantio_id'), fn ($q) => $q->where('aplicacoes.plantio_id', $request->plantio_id))
            ->when($request->filled('safra_id'), fn ($q) => $q->where('pl.safra_id', $request->safra_id))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('a.cliente_id', $request->cliente_id))
            ->when($request->filled('tipo_produto'), fn ($q) => $q->where('aplicacoes.tipo_produto', $request->tipo_produto))
            ->when($request->filled('data_inicio'), fn ($q) => $q->where('aplicacoes.data_aplicacao', '>=', $request->data_inicio))
            ->when($request->filled('data_fim'), fn ($q) => $q->where('aplicacoes.data_aplicacao', '<=', $request->data_fim))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('aplicacoes.produto', 'like', $busca)
                    ->orWhere('a.nome', 'like', $busca)
                    ->orWhere('cl.nome', 'like', $busca)
                    ->orWhere('aplicacoes.observacoes', 'like', $busca));
            })
            ->orderByDesc('aplicacoes.data_aplicacao')
            ->orderByDesc('aplicacoes.id')
            ->get();

        return $this->dados($aplicacoes);
    }

    public function show(Aplicacao $aplicacao): JsonResponse
    {
        return $this->dados(Aplicacao::comResumo()->where('aplicacoes.id', $aplicacao->id)->first());
    }

    /** O usuário logado fica como responsável pela aplicação. */
    public function store(AplicacaoRequest $request): JsonResponse
    {
        $aplicacao = Aplicacao::create($this->campos($request) + ['usuario_id' => $request->user()->id]);

        return $this->criado($aplicacao->id);
    }

    public function update(AplicacaoRequest $request, Aplicacao $aplicacao): JsonResponse
    {
        $aplicacao->update($this->campos($request));

        return $this->ok();
    }

    public function destroy(Aplicacao $aplicacao): JsonResponse
    {
        $aplicacao->delete();

        return $this->ok();
    }

    /** Campos validados + a área, que vem do próprio plantio. */
    private function campos(AplicacaoRequest $request): array
    {
        return $request->validated() + ['area_id' => Plantio::whereKey($request->plantio_id)->value('area_id')];
    }
}
