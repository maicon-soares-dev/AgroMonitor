<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * GET /api/clientes — só clientes ativos.
     * Filtro: busca (nome, documento, telefone ou e-mail).
     */
    public function index(Request $request): JsonResponse
    {
        $clientes = Cliente::where('ativo', 1)
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $busca)
                    ->orWhere('documento', 'like', $busca)
                    ->orWhere('telefone', 'like', $busca)
                    ->orWhere('email', 'like', $busca));
            })
            ->orderBy('nome')
            ->get();

        return $this->dados($clientes);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return $this->dados($cliente);
    }

    public function store(ClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return $this->criado($cliente->id);
    }

    public function update(ClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return $this->ok();
    }

    /** Exclusão lógica (ativo = 0): as áreas do cliente continuam apontando para ele. */
    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->update(['ativo' => 0]);

        return $this->ok();
    }
}
