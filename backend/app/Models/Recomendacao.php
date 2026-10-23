<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recomendacao extends Modelo
{
    protected $table = 'recomendacoes';

    protected $fillable = ['monitoramento_id', 'usuario_id', 'texto'];

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function aplicacoes(): HasMany
    {
        return $this->hasMany(Aplicacao::class, 'recomendacao_id');
    }
}
