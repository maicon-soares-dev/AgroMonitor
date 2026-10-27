<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ponto físico de amostragem da área, reaproveitado safra após safra. */
class PontoMonitoramento extends Modelo
{
    protected $table = 'pontos_monitoramento';

    protected $fillable = [
        'area_id', 'talhao_id', 'numero_ponto', 'latitude', 'longitude', 'data_registro', 'observacoes',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function talhao(): BelongsTo
    {
        return $this->belongsTo(Talhao::class, 'talhao_id');
    }

    public function monitoramentos(): HasMany
    {
        return $this->hasMany(Monitoramento::class, 'ponto_id');
    }
}
