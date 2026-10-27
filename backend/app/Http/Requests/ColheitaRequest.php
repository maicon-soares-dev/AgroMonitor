<?php

namespace App\Http\Requests;

use App\Models\Plantio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColheitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A colheita não pode ser antes do plantio
        $dataPlantio = is_numeric($this->plantio_id) ? Plantio::whereKey($this->plantio_id)->value('data_plantio') : null;
        $dataColheita = ['required', 'date_format:Y-m-d'];
        if ($dataPlantio) {
            $dataColheita[] = 'after_or_equal:'.$dataPlantio;
        }

        return [
            // Só uma colheita por plantio
            'plantio_id' => ['required', 'integer', 'exists:plantios,id',
                Rule::unique('colheitas', 'plantio_id')->ignore($this->route('colheita'))],
            'data_colheita' => $dataColheita,
            'produtividade_sc_ha' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'umidade_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'plantio_id' => 'plantio',
            'data_colheita' => 'data da colheita',
            'produtividade_sc_ha' => 'produtividade (sc/ha)',
            'umidade_pct' => 'umidade',
            'observacoes' => 'observações',
        ];
    }

    public function messages(): array
    {
        return [
            'plantio_id.unique' => 'Esse plantio já tem colheita lançada — edite a existente.',
            'data_colheita.date_format' => 'Informe uma data de colheita válida.',
            'data_colheita.after_or_equal' => 'A data da colheita não pode ser anterior à data do plantio.',
            'produtividade_sc_ha.gt' => 'Informe a produtividade (sc/ha) maior que zero.',
        ];
    }
}
