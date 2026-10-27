<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variedade extends Modelo
{
    protected $table = 'variedades';

    protected $fillable = ['cultura_id', 'nome', 'descricao', 'ativa'];

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class, 'cultura_id');
    }

    public function plantios(): HasMany
    {
        return $this->hasMany(Plantio::class, 'variedade_id');
    }
}
