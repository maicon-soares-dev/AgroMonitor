<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monitoramento de um ponto em referência a um plantio (tabela de movimento,
 * mestre de monitoramento_itens). Id UUID para a sincronização offline.
 */
class Monitoramento extends Modelo
{
    use HasUuids;

    protected $table = 'monitoramentos';

    protected $fillable = [
        'ponto_id', 'plantio_id', 'data_monitoramento', 'estagio_fenologico',
        'milho_plantas_avaliadas', 'milho_plantas_praga', 'milho_percentual',
        'soja_pragas_encontradas', 'soja_metros_lineares', 'soja_pragas_por_m2',
        'resultado_final', 'unidade_resultado', 'nivel_controle', 'observacoes',
    ];

    /**
     * Colunas do monitoramento + área e número do ponto, data do plantio,
     * safra e cultura (formato da API antiga).
     */
    public function scopeComResumo(Builder $query): Builder
    {
        return $query->join('pontos_monitoramento as p', 'p.id', '=', 'monitoramentos.ponto_id')
            ->join('areas as a', 'a.id', '=', 'p.area_id')
            ->join('plantios as pl', 'pl.id', '=', 'monitoramentos.plantio_id')
            ->join('safras as s', 's.id', '=', 'pl.safra_id')
            ->join('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->select('monitoramentos.*', 'p.area_id', 'p.numero_ponto', 'a.nome as area_nome',
                'pl.data_plantio', 'pl.safra_id', 's.nome as safra_nome', 'c.nome as cultura_nome');
    }

    public function ponto(): BelongsTo
    {
        return $this->belongsTo(PontoMonitoramento::class, 'ponto_id');
    }

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(MonitoramentoItem::class, 'monitoramento_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(MonitoramentoFoto::class, 'monitoramento_id');
    }

    public function recomendacoes(): HasMany
    {
        return $this->hasMany(Recomendacao::class, 'monitoramento_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(Aplicacao::class, 'monitoramento_id');
    }
}
