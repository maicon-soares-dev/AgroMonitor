<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros dos relatórios gerenciais (query string). O pivot exige safra ou plantio. */
class RelatorioGerencialRequest extends FormRequest
{
    const TIPOS = ['todos', 'praga', 'doenca', 'planta_daninha', 'clima'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pivot = $this->routeIs('relatorios-gerenciais.pivot');

        return [
            'safra_id' => [$pivot ? 'required_without:plantio_id' : 'nullable', 'integer', 'exists:safras,id'],
            'plantio_id' => ['nullable', 'integer', 'exists:plantios,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'tipo' => ['nullable', Rule::in(self::TIPOS)],
            'medida' => ['nullable', Rule::in(['ocorrencias', 'quantidade'])],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ];
    }

    public function attributes(): array
    {
        return [
            'safra_id' => 'safra',
            'plantio_id' => 'plantio',
            'cliente_id' => 'cliente',
            'area_id' => 'área',
            'tipo' => 'tipo',
            'medida' => 'medida',
            'data_inicio' => 'data inicial',
            'data_fim' => 'data final',
        ];
    }

    public function messages(): array
    {
        return [
            'safra_id.required_without' => 'Escolha uma safra ou um plantio para montar o pivot.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ];
    }

    /** Só os filtros preenchidos. */
    public function filtros(): array
    {
        return array_filter($this->validated(), fn ($v) => $v !== null && $v !== '');
    }
}
