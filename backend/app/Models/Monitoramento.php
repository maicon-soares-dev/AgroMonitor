<?php

namespace App\Models;

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
