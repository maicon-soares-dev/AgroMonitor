<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo de pragas, doenças, plantas daninhas e eventos climáticos (campo
 * `tipo`). cultura_id nulo = vale para qualquer cultura.
 */
class Praga extends Modelo
{
    const TIPOS = ['praga', 'doenca', 'planta_daninha', 'clima'];
    const NIVEIS_RISCO = ['baixo', 'medio', 'alto'];

    protected $table = 'pragas';

    protected $fillable = ['nome', 'tipo', 'cultura_id', 'nivel_risco', 'descricao', 'ativa'];

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class, 'cultura_id');
    }
}
