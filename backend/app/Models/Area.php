<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Área = cadastro fixo do imóvel do cliente. `alqueires` é coluna gerada pelo
 * banco (hectares / 2.42), por isso não está no $fillable.
 */
class Area extends Modelo
{
    protected $table = 'areas';

    protected $fillable = ['cliente_id', 'nome', 'hectares', 'qtd_pontos', 'observacoes', 'geometria'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function talhoes(): HasMany
    {
        return $this->hasMany(Talhao::class, 'area_id');
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(PontoMonitoramento::class, 'area_id');
    }

    public function plantios(): HasMany
    {
        return $this->hasMany(Plantio::class, 'area_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(Aplicacao::class, 'area_id');
    }
}
