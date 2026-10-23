<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cria o primeiro usuário gestor. Login e senha vêm do .env (GESTOR_LOGIN e
 * GESTOR_SENHA); sem senha definida, gera uma aleatória e mostra no console.
 */
class UsuarioGestorSeeder extends Seeder
{
    public function run(): void
    {
        $login = env('GESTOR_LOGIN') ?: 'gestor';
        $senha = env('GESTOR_SENHA');

        if (! $senha) {
            $senha = Str::password(12, symbols: false);
            $this->command?->warn("GESTOR_SENHA não definida no .env. Senha gerada para '{$login}': {$senha}");
        }

        User::updateOrCreate(
            ['login' => $login],
            ['nome' => 'Gestor', 'password' => $senha, 'perfil' => 'gestor', 'ativo' => true],
        );
    }
}
