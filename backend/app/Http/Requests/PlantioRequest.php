<?php

namespace App\Http\Requests;

use App\Models\Plantio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlantioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Talhões e variedades precisam ser da área e da cultura escolhidas
        $talhaoDaArea = Rule::exists('talhoes', 'id')->where('area_id', $this->area_id);
        $variedadeDaCultura = Rule::exists('variedades', 'id')->where('cultura_id', $this->cultura_id);

        return [
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'safra_id' => ['required', 'integer', 'exists:safras,id'],
            'data_plantio' => ['required', 'date_format:Y-m-d'],
            'cultura_id' => ['required', 'integer', 'exists:culturas,id'],
            'variedade_id' => ['nullable', 'integer', $variedadeDaCultura],
            'talhao_id' => ['nullable', 'integer', $talhaoDaArea],
            'populacao_semente' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'adubo_id' => ['nullable', 'integer', 'exists:adubos,id'],
            'adubo_qtd_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'condicao_plantio' => ['nullable', Rule::in(Plantio::CONDICOES_PLANTIO)],
            'condicao_solo' => ['nullable', Rule::in(Plantio::CONDICOES_SOLO)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'talhoes' => ['sometimes', 'nullable', 'array'],
            'talhoes.*.talhao_id' => ['required', 'integer', 'distinct', $talhaoDaArea],
            'talhoes.*.variedade_id' => ['nullable', 'integer', $variedadeDaCultura],
        ];
    }

    public function attributes(): array
    {
        return [
            'area_id' => 'área',
            'safra_id' => 'safra',
            'data_plantio' => 'data do plantio',
            'cultura_id' => 'cultura',
            'variedade_id' => 'variedade',
            'talhao_id' => 'talhão',
            'populacao_semente' => 'população',
            'adubo_id' => 'adubo',
            'adubo_qtd_kg' => 'quantidade de adubo',
            'condicao_plantio' => 'condição do plantio',
            'condicao_solo' => 'condição do solo',
            'observacoes' => 'observações',
            'talhoes.*.talhao_id' => 'talhão',
            'talhoes.*.variedade_id' => 'variedade do talhão',
        ];
    }

    public function messages(): array
    {
        return [
            'variedade_id.exists' => 'A variedade escolhida não é da cultura do plantio.',
            'talhoes.*.variedade_id.exists' => 'A variedade de um dos talhões não é da cultura do plantio.',
            'talhoes.*.talhao_id.exists' => 'Um dos talhões não pertence à área escolhida.',
            'talhao_id.exists' => 'O talhão não pertence à área escolhida.',
            'data_plantio.date_format' => 'Informe uma data de plantio válida.',
        ];
    }
}
