<?php

namespace Database\Seeders;

use App\Models\Safra;
use Illuminate\Database\Seeder;

class SafraSeeder extends Seeder
{
    public function run(): void
    {
        Safra::firstOrCreate(
            ['nome' => 'Safra 2026/2027'],
            ['mes_inicio' => 9, 'ano_inicio' => 2026, 'mes_fim' => 9, 'ano_fim' => 2027, 'ativa' => true],
        );
    }
}
