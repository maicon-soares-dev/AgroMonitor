<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ponto de monitoramento: na criação, área e número são obrigatórios; na
 * edição (arrastar no mapa, renumerar) só os campos enviados mudam.
 */
class PontoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ponto = $this->route('ponto');
        $areaId = $ponto ? $ponto->area_id : $this->area_id;

        $numeroUnico = Rule::unique('pontos_monitoramento', 'numero_ponto')
            ->where('area_id', $areaId)
            ->ignore($ponto);

        return [
            'area_id' => [$ponto ? 'prohibited' : 'required', 'integer', 'exists:areas,id'],
            'talhao_id' => ['sometimes', 'nullable', 'integer', Rule::exists('talhoes', 'id')->where('area_id', $areaId)],
            'numero_ponto' => [$ponto ? 'sometimes' : 'required', 'integer', 'min:1', $numeroUnico],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'area_id' => 'área',
            'talhao_id' => 'talhão',
            'numero_ponto' => 'número do ponto',
            'observacoes' => 'observações',
        ];
    }

    public function messages(): array
    {
        return [
            'area_id.prohibited' => 'Não é possível mudar o ponto de área.',
            'numero_ponto.unique' => 'Esta área já tem um ponto com este número.',
            'talhao_id.exists' => 'O talhão informado não pertence a esta área.',
        ];
    }
}
