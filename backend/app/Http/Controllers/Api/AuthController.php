<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /api/login — devolve o token Bearer e os dados do usuário. */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = User::where('login', $request->login)->first();

        if (! $usuario || ! Hash::check($request->senha, $usuario->password)) {
            throw ValidationException::withMessages(['login' => 'Login ou senha inválidos.']);
        }

        if (! $usuario->ativo) {
            throw ValidationException::withMessages(['login' => 'Usuário inativo. Fale com o gestor do sistema.']);
        }

        $token = $usuario->createToken($request->dispositivo ?: 'web')->plainTextToken;

        return $this->dados([
            'token' => $token,
            'usuario' => $this->resumo($usuario),
        ]);
    }

    /** POST /api/logout — invalida só o token usado nesta requisição. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok();
    }

    /** GET /api/me — usuário dono do token. */
    public function me(Request $request): JsonResponse
    {
        return $this->dados($this->resumo($request->user()));
    }

    private function resumo(User $usuario): array
    {
        return $usuario->only(['id', 'nome', 'login', 'email', 'perfil']);
    }
}
