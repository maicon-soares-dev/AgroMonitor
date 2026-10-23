<?php

namespace Database\Seeders;

use App\Models\Adubo;
use Illuminate\Database\Seeder;

class AduboSeeder extends Seeder
{
    public function run(): void
    {
        $adubos = [
            ['NPK 20-20-20', 'Fertilizante Misto', '20-20-20', 'Adubo formulado balanceado'],
            ['Uréia', 'Nitrogenado', '46-0-0', 'Fonte de nitrogênio'],
            ['MAP', 'Fosfatado', '0-46-0', 'Monoamônio fosfato'],
            ['KCl', 'Potássio', '0-0-60', 'Cloreto de potássio'],
            ['Ureia + Polímero', 'Nitrogenado', '46-0-0', 'Ureia revestida'],
            ['Superfosfato Simples', 'Fosfatado', '0-18-0', 'Fertilizante tradicional'],
            ['Cloreto de Potássio Granulado', 'Potássio', '0-0-60', 'Formulação granular'],
        ];

        foreach ($adubos as [$nome, $tipo, $npk, $descricao]) {
            Adubo::firstOrCreate(['nome' => $nome], compact('tipo', 'npk', 'descricao') + ['ativa' => true]);
        }
    }
}
