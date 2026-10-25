<?php

namespace App\Models;

use App\Services\PlantioService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Colheita de um plantio (no máximo uma por plantio). */
class Colheita extends Modelo
{
    protected $table = 'colheitas';

    protected $fillable = ['plantio_id', 'data_colheita', 'produtividade_sc_ha', 'umidade_pct', 'observacoes'];

    /**
     * Colunas da colheita + dados do plantio, hectares da área, cliente,
     * cultura, safra e variedade(s) (formato da API antiga).
     */
    public function scopeComResumo(Builder $query): Builder
    {
        return $query->join('plantios as pl', 'pl.id', '=', 'colheitas.plantio_id')
            ->join('areas as a', 'a.id', '=', 'pl.area_id')
            ->join('clientes as cl', 'cl.id', '=', 'a.cliente_id')
            ->join('culturas as c', 'c.id', '=', 'pl.cultura_id')
            ->join('safras as s', 's.id', '=', 'pl.safra_id')
            ->leftJoin('variedades as v', 'v.id', '=', 'pl.variedade_id')
            ->select('colheitas.*', 'pl.data_plantio', 'pl.area_id', 'a.nome as area_nome', 'a.hectares',
                'cl.nome as cliente_nome', 'c.nome as cultura_nome', 's.nome as safra_nome')
            ->selectRaw(PlantioService::sqlVariedadeNome('pl', 'v').' as variedade_nome');
    }

    public function plantio(): BelongsTo
    {
        return $this->belongsTo(Plantio::class, 'plantio_id');
    }
}
