<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AduboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100', Rule::unique('adubos', 'nome')->ignore($this->route('adubo'))],
            'tipo' => ['nullable', 'string', 'max:50'],
            'npk' => ['nullable', 'string', 'max:20'],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'tipo' => 'tipo', 'npk' => 'NPK', 'descricao' => 'descrição'];
    }

    public function messages(): array
    {
        return ['nome.unique' => 'Já existe um adubo com este nome.'];
    }
}
