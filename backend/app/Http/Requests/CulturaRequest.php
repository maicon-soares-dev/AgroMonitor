<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CulturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:50', Rule::unique('culturas', 'nome')->ignore($this->route('cultura'))],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'descricao' => 'descrição'];
    }

    public function messages(): array
    {
        return ['nome.unique' => 'Já existe uma cultura com este nome.'];
    }
}
