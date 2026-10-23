<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Adubo extends Modelo
{
    protected $table = 'adubos';

    protected $fillable = ['nome', 'tipo', 'npk', 'descricao', 'ativa'];

    public function plantios(): HasMany
    {
        return $this->hasMany(Plantio::class, 'adubo_id');
    }
}
