<?php

namespace App\Http\Requests;

use App\Models\Aplicacao;
use App\Models\Plantio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AplicacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A área vem do plantio; talhão e monitoramento (opcionais) precisam ser dele
        $areaDoPlantio = is_numeric($this->plantio_id) ? Plantio::whereKey($this->plantio_id)->value('area_id') : null;

        return [
            'plantio_id' => ['required', 'integer', 'exists:plantios,id'],
            'data_aplicacao' => ['required', 'date_format:Y-m-d'],
            'produto' => ['required', 'string', 'max:150'],
            'tipo_produto' => ['nullable', Rule::in(Aplicacao::TIPOS)],
            'dose' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'unidade_dose' => ['nullable', 'string', 'max:20'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'talhao_id' => ['nullable', 'integer', Rule::exists('talhoes', 'id')->where('area_id', $areaDoPlantio)],
            'monitoramento_id' => ['nullable', 'uuid', Rule::exists('monitoramentos', 'id')->where('plantio_id', $this->plantio_id)],
        ];
    }

    public function attributes(): array
    {
        return [
            'plantio_id' => 'plantio',
            'data_aplicacao' => 'data da aplicação',
            'produto' => 'produto',
            'tipo_produto' => 'tipo',
            'dose' => 'dose',
            'unidade_dose' => 'unidade',
            'observacoes' => 'observações',
            'talhao_id' => 'talhão',
            'monitoramento_id' => 'monitoramento',
        ];
    }

    public function messages(): array
    {
        return [
            'data_aplicacao.date_format' => 'Informe uma data de aplicação válida.',
            'talhao_id.exists' => 'O talhão não pertence à área do plantio.',
            'monitoramento_id.exists' => 'O monitoramento não é deste plantio.',
        ];
    }
}
