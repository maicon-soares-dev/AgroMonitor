<?php

namespace App\Http\Requests;

use App\Models\Praga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PragaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100', Rule::unique('pragas', 'nome')->ignore($this->route('praga'))],
            'tipo' => ['nullable', Rule::in(Praga::TIPOS)],
            'cultura_id' => ['nullable', 'integer', 'exists:culturas,id'],
            'nivel_risco' => ['nullable', Rule::in(Praga::NIVEIS_RISCO)],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'tipo' => 'tipo',
            'cultura_id' => 'cultura',
            'nivel_risco' => 'nível de risco',
            'descricao' => 'descrição',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.unique' => 'Já existe um item com este nome no catálogo.',
            'tipo.in' => 'Tipo inválido. Use praga, doença, planta daninha ou clima.',
            'nivel_risco.in' => 'Nível de risco inválido. Use baixo, médio ou alto.',
        ];
    }
}
