<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Cultura extends Modelo
{
    protected $table = 'culturas';

    protected $fillable = ['nome', 'descricao', 'ativa'];

    public function variedades(): HasMany
    {
        return $this->hasMany(Variedade::class, 'cultura_id');
    }

    public function pragas(): HasMany
    {
        return $this->hasMany(Praga::class, 'cultura_id');
    }

    public function plantios(): HasMany
    {
        return $this->hasMany(Plantio::class, 'cultura_id');
    }
}
