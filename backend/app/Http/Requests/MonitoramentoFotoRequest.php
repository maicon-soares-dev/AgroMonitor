<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Upload (POST, multipart) e edição de favorita/legenda (PUT) das fotos. */
class MonitoramentoFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'monitoramento_id' => ['required', 'uuid', 'exists:monitoramentos,id'],
                'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
                'descricao' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            'favorita' => ['sometimes', 'boolean'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'monitoramento_id' => 'monitoramento',
            'descricao' => 'legenda',
        ];
    }

    public function messages(): array
    {
        return [
            'monitoramento_id.exists' => 'Monitoramento não encontrado.',
            'foto.required' => 'Nenhuma foto enviada.',
            'foto.uploaded' => 'Falha no envio da foto (máximo 8 MB).',
            'foto.image' => 'O arquivo não é uma imagem válida.',
            'foto.mimes' => 'Formato de imagem não aceito (use JPG, PNG ou WEBP).',
            'foto.max' => 'Foto muito grande (máximo 8 MB).',
        ];
    }
}
