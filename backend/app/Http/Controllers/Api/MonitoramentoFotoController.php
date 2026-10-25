<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MonitoramentoFotoRequest;
use App\Models\MonitoramentoFoto;
use App\Services\MonitoramentoService;
use Illuminate\Http\JsonResponse;

/**
 * Fotos do monitoramento. O upload (POST) vem em multipart/form-data com
 * monitoramento_id, foto e descricao; a lista sai junto no GET /monitoramentos.
 */
class MonitoramentoFotoController extends Controller
{
    public function __construct(private MonitoramentoService $monitoramentos)
    {
    }

    /** Resposta como a da API antiga: id, arquivo e url. */
    public function store(MonitoramentoFotoRequest $request): JsonResponse
    {
        $foto = $this->monitoramentos->adicionarFoto(
            $request->monitoramento_id,
            $request->file('foto'),
            $request->validated('descricao')
        );

        return response()->json([
            'sucesso' => true,
            'id' => $foto->id,
            'arquivo' => $foto->arquivo,
            'url' => $foto->url,
        ]);
    }

    /** Favorita (entra no relatório do dia) e/ou legenda — só o que vier no corpo. */
    public function update(MonitoramentoFotoRequest $request, MonitoramentoFoto $foto): JsonResponse
    {
        $campos = $request->validated();
        if (! $campos) {
            return $this->erro('Nada para atualizar');
        }

        $foto->update($campos);

        return $this->ok();
    }

    public function destroy(MonitoramentoFoto $foto): JsonResponse
    {
        $this->monitoramentos->excluirFoto($foto);

        return $this->ok();
    }
}
