<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Safra extends Modelo
{
    protected $table = 'safras';

    protected $fillable = ['nome', 'mes_inicio', 'ano_inicio', 'mes_fim', 'ano_fim', 'ativa'];

    public function plantios(): HasMany
    {
        return $this->hasMany(Plantio::class, 'safra_id');
    }
}
