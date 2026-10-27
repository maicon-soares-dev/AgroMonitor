<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'nome' => ['required', 'string', 'max:150'],
            'hectares' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'qtd_pontos' => ['nullable', 'integer', 'between:1,500'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'geometria' => ['nullable', 'array'],
            // Coordenadas dos pontos calculadas pelo front dentro do polígono: [[lat, lng], ...]
            'pontos_coords' => ['nullable', 'array'],
            'pontos_coords.*' => ['array', 'size:2'],
            'pontos_coords.*.*' => ['nullable', 'numeric'],
            // Talhões desenhados já no cadastro da área
            'talhoes' => ['nullable', 'array'],
            'talhoes.*.nome' => ['nullable', 'string', 'max:100'],
            'talhoes.*.cor_hex' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'talhoes.*.hectares' => ['nullable', 'numeric', 'min:0'],
            'talhoes.*.geometria' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'nome' => 'nome da área',
            'hectares' => 'hectares',
            'qtd_pontos' => 'pontos de monitoramento',
            'observacoes' => 'observações',
            'talhoes.*.nome' => 'nome do talhão',
            'talhoes.*.cor_hex' => 'cor do talhão',
        ];
    }

    public function messages(): array
    {
        return ['hectares.gt' => 'A área precisa ter mais que 0 hectares.'];
    }
}
