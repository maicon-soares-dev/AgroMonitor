<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aplicação de defensivo, adubo ou corretivo numa área/talhão/plantio. */
class Aplicacao extends Modelo
{
    protected $table = 'aplicacoes';

    protected $fillable = [
        'area_id', 'talhao_id', 'plantio_id', 'monitoramento_id', 'recomendacao_id', 'produto',
        'tipo_produto', 'dose', 'unidade_dose', 'data_aplicacao', 'usuario_id', 'observacoes',
    ];

    const TIPOS = ['defensivo', 'adubo', 'corretivo'];

    /** Colunas da aplicação + área, cliente, cultura e safra do plantio (formato da API antiga). */
    public function scopeComResumo(Builder $query): Builder
    {
        return $query->join('areas as a', 'a.id', '=', 'aplicacoes.area_id')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->leftJoin('plantios as pl', 'pl.id', '=', 'aplicacoes.plantio_id')
            ->leftJoin('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->leftJoin('safras as s', 's.id', '=', 'pl.safra_id')
            ->select('aplicacoes.*', 'a.nome as area_nome', 'cl.nome as cliente_nome',
                'c.nome as cultura_nome', 's.nome as safra_nome');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function talhao(): BelongsTo
    {
        return $this->belongsTo(Talhao::class, 'talhao_id');
    }

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }

    public function recomendacao(): BelongsTo
    {
        return $this->belongsTo(Recomendacao::class, 'recomendacao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
