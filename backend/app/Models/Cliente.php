<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Modelo
{
    protected $table = 'clientes';

    protected $fillable = ['usuario_id', 'nome', 'documento', 'telefone', 'email', 'ativo'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'cliente_id');
    }
}
