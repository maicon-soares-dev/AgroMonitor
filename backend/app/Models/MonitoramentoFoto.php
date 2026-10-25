<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Foto anexada ao monitoramento; o arquivo fica no disco "public". */
class MonitoramentoFoto extends Modelo
{
    /** Pasta das fotos dentro de storage/app/public. */
    const PASTA = 'monitoramento_fotos';

    use HasUuids;

    protected $table = 'monitoramento_fotos';

    protected $fillable = ['monitoramento_id', 'arquivo', 'descricao', 'favorita'];

    /** URL completa da foto, pronta para um <img src>. */
    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url(self::PASTA.'/'.$this->arquivo);
    }

    public function monitoramento(): BelongsTo
    {
        return $this->belongsTo(Monitoramento::class, 'monitoramento_id');
    }
}
