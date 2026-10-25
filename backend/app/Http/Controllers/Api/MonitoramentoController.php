<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonitoramentoRequest;
use App\Models\Monitoramento;
use App\Services\MonitoramentoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Monitoramentos (movimento) com os itens e as fotos de cada visita. */
class MonitoramentoController extends Controller
{
    public function __construct(private MonitoramentoService $monitoramentos)
    {
    }

    /**
     * GET /api/monitoramentos — do mais recente para o mais antigo, com
     * "itens" e "fotos" (com url). Filtros: area_id, plantio_id, safra_id,
     * nivel_controle, data_inicio, data_fim.
     */
    public function index(Request $request): JsonResponse
    {
        $consulta = Monitoramento::comResumo()
            ->when($request->filled('area_id'), fn ($q) => $q->where('p.area_id', $request->area_id))
            ->when($request->filled('plantio_id'), fn ($q) => $q->where('monitoramentos.plantio_id', $request->plantio_id))
            ->when($request->filled('safra_id'), fn ($q) => $q->where('pl.safra_id', $request->safra_id))
            ->when($request->filled('nivel_controle'), fn ($q) => $q->where('monitoramentos.nivel_controle', $request->nivel_controle))
            ->when($request->filled('data_inicio'), fn ($q) => $q->where('monitoramentos.data_monitoramento', '>=', $request->data_inicio))
            ->when($request->filled('data_fim'), fn ($q) => $q->where('monitoramentos.data_monitoramento', '<=', $request->data_fim));

        return $this->dados($this->comItensEFotos($consulta));
    }

    /** GET /api/monitoramentos/{id} — como na API antiga, "dados" é uma lista com um item. */
    public function show(Monitoramento $monitoramento): JsonResponse
    {
        return $this->dados($this->comItensEFotos(
            Monitoramento::comResumo()->where('monitoramentos.id', $monitoramento->id)
        ));
    }

    public function store(MonitoramentoRequest $request): JsonResponse
    {
        $monitoramento = $this->monitoramentos->criar($request->validated());

        return $this->criado($monitoramento->id);
    }

    public function update(MonitoramentoRequest $request, Monitoramento $monitoramento): JsonResponse
    {
        $this->monitoramentos->atualizar($monitoramento, $request->validated());

        return $this->ok();
    }

    /** Apaga junto os itens e as fotos (CASCADE + arquivos). */
    public function destroy(Monitoramento $monitoramento): JsonResponse
    {
        $this->monitoramentos->excluir($monitoramento);

        return $this->ok();
    }

    private function comItensEFotos(Builder $consulta): array
    {
        return $consulta
            ->with([
                'itens' => fn ($q) => $q->orderBy('id'),
                'fotos' => fn ($q) => $q->orderBy('id'),
            ])
            ->orderByDesc('monitoramentos.data_monitoramento')
            ->orderBy('p.numero_ponto')
            ->get()
            ->toArray();
    }
}
