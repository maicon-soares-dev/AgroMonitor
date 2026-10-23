<?php

namespace App\Models;

use Database\Factories\UserFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuário do sistema. Login por `login` + senha; `perfil` define o acesso
 * (gestor | agronomo | tecnico).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';

    const PERFIS = ['gestor', 'agronomo', 'tecnico'];

    protected $fillable = ['nome', 'login', 'email', 'password', 'perfil', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'usuario_id');
    }
}
