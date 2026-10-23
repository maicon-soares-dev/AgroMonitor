<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Talhao extends Modelo
{
    protected $table = 'talhoes';

    protected $fillable = ['area_id', 'nome', 'variedade', 'tipo', 'hectares', 'cor_hex', 'geometria'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(PontoMonitoramento::class, 'talhao_id');
    }

    public function plantioTalhoes(): HasMany
    {
        return $this->hasMany(PlantioTalhao::class, 'talhao_id');
    }
}
