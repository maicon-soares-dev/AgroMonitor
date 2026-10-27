<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Talhão: na criação a área é obrigatória; na edição (feita pelo editor do
 * mapa) só os campos enviados são validados e alterados.
 */
class TalhaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $editando = $this->route('talhao') !== null;

        return [
            'area_id' => [$editando ? 'prohibited' : 'required', 'integer', 'exists:areas,id'],
            'nome' => $editando ? ['sometimes', 'required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'cor_hex' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hectares' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'geometria' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return ['area_id' => 'área', 'nome' => 'nome do talhão', 'cor_hex' => 'cor', 'hectares' => 'hectares'];
    }

    public function messages(): array
    {
        return [
            'area_id.prohibited' => 'Não é possível mudar o talhão de área.',
            'cor_hex.regex' => 'A cor deve estar no formato #RRGGBB.',
        ];
    }
}
