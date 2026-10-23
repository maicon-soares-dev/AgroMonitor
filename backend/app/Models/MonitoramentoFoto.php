<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoramentoFoto extends Modelo
{
    use HasUuids;

    protected $table = 'monitoramento_fotos';

    protected $fillable = ['monitoramento_id', 'arquivo', 'descricao', 'favorita'];

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }
}
