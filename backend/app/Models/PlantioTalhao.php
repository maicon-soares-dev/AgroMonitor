<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Variedade plantada em cada talhão de um plantio. */
class PlantioTalhao extends Modelo
{
    protected $table = 'plantio_talhoes';

    protected $fillable = ['plantio_id', 'talhao_id', 'variedade_id'];

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }

    public function talhao(): BelongsTo
    {
        return $this->belongsTo(Talhao::class, 'talhao_id');
    }

    public function variedade(): BelongsTo
    {
        return $this->belongsTo(Variedade::class, 'variedade_id');
    }
}
