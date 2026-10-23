<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Colheita de um plantio (no máximo uma por plantio). */
class Colheita extends Modelo
{
    protected $table = 'colheitas';

    protected $fillable = ['plantio_id', 'data_colheita', 'produtividade_sc_ha', 'umidade_pct', 'observacoes'];

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }
}
