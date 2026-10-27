<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Histórico de relatórios gerados (opcional). Usa `gerado_em` como data de criação. */
class Relatorio extends Modelo
{
    const CREATED_AT = 'gerado_em';

    protected $table = 'relatorios';

    protected $fillable = ['area_id', 'safra_id', 'plantio_id', 'tipo', 'arquivo_path', 'gerado_por'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function safra(): BelongsTo
    {
        return $this->belongsTo(Safra::class, 'safra_id');
    }

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por');
    }
}
