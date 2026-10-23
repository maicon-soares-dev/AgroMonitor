<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro e edição de usuários (só gestor). Na edição, a senha é opcional. */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $editando = $usuario !== null;

        return [
            'nome' => ['required', 'string', 'max:150'],
            'login' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'login')->ignore($usuario)],
            'email' => ['nullable', 'email', 'max:150'],
            'senha' => [$editando ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
            'perfil' => ['required', Rule::in(User::PERFIS)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'login' => 'login',
            'email' => 'e-mail',
            'senha' => 'senha',
            'perfil' => 'perfil',
            'ativo' => 'situação',
        ];
    }

    public function messages(): array
    {
        return [
            'login.unique' => 'Este login já está em uso.',
            'login.alpha_dash' => 'O login só pode ter letras, números, hífen e sublinhado (sem espaços).',
            'perfil.in' => 'Perfil inválido. Use gestor, agrônomo ou técnico.',
        ];
    }
}
