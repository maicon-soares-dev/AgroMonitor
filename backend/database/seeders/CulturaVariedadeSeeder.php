<?php

namespace Database\Seeders;

use App\Models\Cultura;
use Illuminate\Database\Seeder;

class CulturaVariedadeSeeder extends Seeder
{
    public function run(): void
    {
        $culturas = [
            'Milho' => [
                'descricao' => 'Milho para grãos',
                'variedades' => [
                    'AG1051' => 'Ciclo precoce',
                    'AG7010' => 'Ciclo normal',
                    'P30F35' => 'Ciclo normal',
                    '30F35' => 'Ciclo super precoce',
                    'DKB230' => 'Ciclo semi-tardio',
                ],
            ],
            'Soja' => [
                'descricao' => 'Soja para grãos',
                'variedades' => [
                    'M6211' => 'Grupo 6.1',
                    'M7371' => 'Grupo 7.3',
                    'BM3975' => 'Grupo 3.9',
                    'NK7059' => 'Grupo 7.0',
                    'NIDERA5909' => 'Grupo 5.9',
                ],
            ],
        ];

        foreach ($culturas as $nome => $dados) {
            $cultura = Cultura::firstOrCreate(['nome' => $nome], ['descricao' => $dados['descricao'], 'ativa' => true]);

            foreach ($dados['variedades'] as $variedade => $descricao) {
                $cultura->variedades()->firstOrCreate(['nome' => $variedade], ['descricao' => $descricao, 'ativa' => true]);
            }
        }
    }
}
