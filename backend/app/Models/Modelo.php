<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base dos models do AgroMonitor: timestamps com os nomes em português do
 * banco (criado_em / atualizado_em).
 */
abstract class Modelo extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';
}
