<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Praga, doença, planta daninha ou condição climática encontrada num monitoramento. */
class MonitoramentoItem extends Modelo
{
    use HasUuids;

    protected $table = 'monitoramento_itens';

    protected $fillable = [
        'monitoramento_id', 'tipo', 'nome', 'quantidade', 'severidade', 'latitude', 'longitude', 'observacoes',
    ];

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }
}
