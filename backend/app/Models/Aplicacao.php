<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aplicação de defensivo, adubo ou corretivo numa área/talhão/plantio. */
class Aplicacao extends Modelo
{
    protected $table = 'aplicacoes';

    protected $fillable = [
        'area_id', 'talhao_id', 'plantio_id', 'monitoramento_id', 'recomendacao_id', 'produto',
        'tipo_produto', 'dose', 'unidade_dose', 'data_aplicacao', 'usuario_id', 'observacoes',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function talhao(): BelongsTo
    {
        return $this->belongsTo(Talhao::class, 'talhao_id');
    }

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }

    public function recomendacao(): BelongsTo
    {
        return $this->belongsTo(Recomendacao::class, 'recomendacao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
