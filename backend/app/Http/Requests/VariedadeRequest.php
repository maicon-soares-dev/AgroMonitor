<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariedadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cultura_id' => ['required', 'integer', 'exists:culturas,id'],
            // O nome se repete entre culturas, mas não dentro da mesma cultura
            'nome' => ['required', 'string', 'max:100', Rule::unique('variedades', 'nome')
                ->where('cultura_id', $this->cultura_id)
                ->ignore($this->route('variedade'))],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['cultura_id' => 'cultura', 'nome' => 'nome', 'descricao' => 'descrição'];
    }

    public function messages(): array
    {
        return ['nome.unique' => 'Esta cultura já tem uma variedade com este nome.'];
    }
}
