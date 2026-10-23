<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados iniciais do AgroMonitor: usuário gestor e catálogos.
     */
    public function run(): void
    {
        $this->call([
            UsuarioGestorSeeder::class,
            SafraSeeder::class,
            CulturaVariedadeSeeder::class,
            AduboSeeder::class,
            PragaSeeder::class,
        ]);
    }
}
