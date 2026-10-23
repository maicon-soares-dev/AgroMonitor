<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos models do AgroMonitor: timestamps com os nomes em português do
 * banco (criado_em / atualizado_em) e datas no JSON no formato da API antiga
 * ("2026-10-23 17:08:29", horário local).
 */
abstract class Modelo extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
