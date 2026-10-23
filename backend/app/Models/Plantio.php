<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Plantio = um ciclo de cultivo (cultura + safra + área). A variedade pode ser
 * única (variedade_id) ou por talhão (plantio_talhoes).
 */
class Plantio extends Modelo
{
    protected $table = 'plantios';

    protected $fillable = [
        'area_id', 'talhao_id', 'safra_id', 'data_plantio', 'cultura_id', 'variedade_id',
        'populacao_semente', 'adubo_id', 'adubo_qtd_kg', 'condicao_plantio', 'condicao_solo',
        'observacoes',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function talhao(): BelongsTo
    {
        return $this->belongsTo(Talhao::class, 'talhao_id');
    }

    public function safra(): BelongsTo
    {
        return $this->belongsTo(Safra::class, 'safra_id');
    }

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class, 'cultura_id');
    }

    public function variedade(): BelongsTo
    {
        return $this->belongsTo(Variedade::class, 'variedade_id');
    }

    public function adubo(): BelongsTo
    {
        return $this->belongsTo(Adubo::class, 'adubo_id');
    }

    /** Talhões do plantio com a variedade de cada um (tabela plantio_talhoes). */
    public function talhoes(): BelongsToMany
    {
        return $this->belongsToMany(Talhao::class, 'plantio_talhoes', 'plantio_id', 'talhao_id')
            ->withPivot('variedade_id')
            ->withTimestamps();
    }

    public function plantioTalhoes(): HasMany
    {
        return $this->hasMany(PlantioTalhao::class, 'plantio_id');
    }

    public function monitoramentos(): HasMany
    {
        return $this->hasMany(Monitoramento::class, 'plantio_id');
    }

    public function colheita(): HasOne
    {
        return $this->hasOne(Colheita::class, 'plantio_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(Aplicacao::class, 'plantio_id');
    }
}
