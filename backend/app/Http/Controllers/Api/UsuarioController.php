<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CRUD de usuários — rotas liberadas só para o perfil gestor. */
class UsuarioController extends Controller
{
    /**
     * GET /api/usuarios
     * Filtros: busca (nome, login ou e-mail), perfil, ativo (1 | 0).
     */
    public function index(Request $request): JsonResponse
    {
        $usuarios = User::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = '%'.$request->busca.'%';
                $q->where(fn ($w) => $w->where('nome', 'like', $busca)
                    ->orWhere('login', 'like', $busca)
                    ->orWhere('email', 'like', $busca));
            })
            ->when($request->filled('perfil'), fn ($q) => $q->where('perfil', $request->perfil))
            ->when($request->filled('ativo'), fn ($q) => $q->where('ativo', $request->boolean('ativo')))
            ->orderBy('nome')
            ->get(['id', 'nome', 'login', 'email', 'perfil', 'ativo', 'criado_em']);

        return $this->dados($usuarios);
    }

    public function show(User $usuario): JsonResponse
    {
        return $this->dados($usuario->only(['id', 'nome', 'login', 'email', 'perfil', 'ativo', 'criado_em']));
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        $usuario = User::create($this->campos($request) + ['ativo' => $request->boolean('ativo', true)]);

        return $this->criado($usuario->id);
    }

    public function update(UsuarioRequest $request, User $usuario): JsonResponse
    {
        $campos = $this->campos($request);
        if ($request->has('ativo')) {
            $campos['ativo'] = $request->boolean('ativo');
        }

        // O gestor não pode tirar o próprio acesso (ficaria sem ninguém para desfazer)
        if ($usuario->is($request->user()) && (($campos['perfil'] ?? 'gestor') !== 'gestor' || ($campos['ativo'] ?? true) === false)) {
            return $this->erro('Você não pode mudar o seu próprio perfil nem se desativar.', 422);
        }

        $usuario->update($campos);

        // Usuário desativado perde as sessões abertas
        if (! $usuario->ativo) {
            $usuario->tokens()->delete();
        }

        return $this->ok();
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        if ($usuario->is($request->user())) {
            return $this->erro('Você não pode excluir o próprio usuário.', 422);
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        return $this->ok();
    }

    /** Campos do formulário; `senha` vira `password` (o cast do model gera o hash). */
    private function campos(UsuarioRequest $request): array
    {
        $campos = $request->safe()->only(['nome', 'login', 'email', 'perfil']);
        if ($request->filled('senha')) {
            $campos['password'] = $request->senha;
        }

        return $campos;
    }
}
