<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SafraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:50', Rule::unique('safras', 'nome')->ignore($this->route('safra'))],
            'mes_inicio' => ['nullable', 'integer', 'between:1,12'],
            'ano_inicio' => ['required', 'integer', 'between:2000,2100'],
            'mes_fim' => ['nullable', 'integer', 'between:1,12'],
            'ano_fim' => ['required', 'integer', 'between:2000,2100'],
            'ativa' => ['sometimes', 'boolean'],
        ];
    }

    /** O fim da safra não pode vir antes do início. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['mes_inicio', 'ano_inicio', 'mes_fim', 'ano_fim'])) {
                    return;
                }
                $inicio = $this->ano_inicio * 12 + ($this->mes_inicio ?? 1);
                $fim = $this->ano_fim * 12 + ($this->mes_fim ?? 12);
                if ($fim < $inicio) {
                    $validator->errors()->add('ano_fim', 'O fim da safra deve ser depois do início.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome da safra',
            'mes_inicio' => 'mês de início',
            'ano_inicio' => 'início da safra',
            'mes_fim' => 'mês de fim',
            'ano_fim' => 'fim da safra',
            'ativa' => 'ativa',
        ];
    }

    public function messages(): array
    {
        return ['nome.unique' => 'Já existe uma safra com este nome.'];
    }
}
