<?php

namespace App\Http\Requests;

use App\Models\Plantio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonitoramentoRequest extends FormRequest
{
    const NIVEIS = ['normal', 'atencao', 'critico'];
    const TIPOS_ITEM = ['praga', 'doenca', 'planta_daninha', 'clima'];
    const SEVERIDADES = ['leve', 'moderada', 'severa'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // O ponto precisa ser da área do plantio escolhido
        $areaDoPlantio = is_numeric($this->plantio_id) ? Plantio::whereKey($this->plantio_id)->value('area_id') : null;
        $plantasPraga = ['nullable', 'integer', 'min:0'];
        if (is_numeric($this->milho_plantas_avaliadas)) {
            $plantasPraga[] = 'max:'.(int) $this->milho_plantas_avaliadas;
        }

        return [
            'ponto_id' => ['required', 'integer', Rule::exists('pontos_monitoramento', 'id')->where('area_id', $areaDoPlantio)],
            'plantio_id' => ['required', 'integer', 'exists:plantios,id'],
            'data_monitoramento' => ['required', 'date_format:Y-m-d'],
            'estagio_fenologico' => ['nullable', 'string', 'max:20'],
            'milho_plantas_avaliadas' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'milho_plantas_praga' => $plantasPraga,
            'soja_pragas_encontradas' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'soja_metros_lineares' => ['nullable', 'numeric', 'gt:0', 'max:999.9'],
            'resultado_final' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'unidade_resultado' => ['nullable', 'string', 'max:20'],
            'nivel_controle' => ['nullable', Rule::in(self::NIVEIS)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'itens' => ['sometimes', 'nullable', 'array'],
            'itens.*.tipo' => ['nullable', Rule::in(self::TIPOS_ITEM)],
            'itens.*.nome' => ['nullable', 'string', 'max:150'],
            'itens.*.quantidade' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'itens.*.severidade' => ['nullable', Rule::in(self::SEVERIDADES)],
            'itens.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'itens.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'itens.*.observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ponto_id' => 'ponto de monitoramento',
            'plantio_id' => 'plantio',
            'data_monitoramento' => 'data do monitoramento',
            'estagio_fenologico' => 'estágio fenológico',
            'milho_plantas_avaliadas' => 'plantas avaliadas',
            'milho_plantas_praga' => 'plantas com praga',
            'soja_pragas_encontradas' => 'pragas encontradas',
            'soja_metros_lineares' => 'metros lineares',
            'resultado_final' => 'resultado',
            'unidade_resultado' => 'unidade do resultado',
            'nivel_controle' => 'nível de controle',
            'observacoes' => 'observações',
            'itens.*.tipo' => 'tipo do item',
            'itens.*.nome' => 'nome do item',
            'itens.*.quantidade' => 'quantidade do item',
            'itens.*.severidade' => 'severidade do item',
            'itens.*.observacoes' => 'local/observação do item',
        ];
    }

    public function messages(): array
    {
        return [
            'ponto_id.exists' => 'O ponto escolhido não pertence à área do plantio.',
            'data_monitoramento.date_format' => 'Informe uma data de monitoramento válida.',
            'milho_plantas_praga.max' => 'Plantas com praga não pode passar do número de plantas avaliadas.',
        ];
    }
}
