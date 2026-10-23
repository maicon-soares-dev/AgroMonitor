<?php

namespace Database\Seeders;

use App\Models\Cultura;
use App\Models\Praga;
use Illuminate\Database\Seeder;

/**
 * Catálogo de pragas, doenças, plantas daninhas e eventos climáticos.
 * Cultura null = vale para qualquer cultura.
 */
class PragaSeeder extends Seeder
{
    public function run(): void
    {
        $culturas = Cultura::pluck('id', 'nome');

        // [nome, tipo, cultura, nivel_risco, descricao]
        $pragas = [
            ['Lagarta-do-cartucho', 'praga', 'Milho', 'alto', 'Praga importante'],
            ['Broca-do-colmo', 'praga', 'Milho', 'medio', 'Perfura o colmo'],
            ['Largata-militar', 'praga', 'Milho', 'medio', 'Alimenta-se das folhas'],
            ['Mosca-branca (Milho)', 'praga', 'Milho', 'baixo', 'Vetor de viroses'],
            ['Ácaro-rajado (Milho)', 'praga', 'Milho', 'baixo', 'Dano às folhas'],
            ['Percevejo-marrom', 'praga', 'Soja', 'alto', 'Principal praga'],
            ['Mosca-branca (Soja)', 'praga', 'Soja', 'medio', 'Vetor de viroses'],
            ['Ácaro-rajado (Soja)', 'praga', 'Soja', 'medio', 'Reduz folhas'],
            ['Lagarta-falsa-medideira', 'praga', 'Soja', 'alto', 'Desfolhadora'],
            ['Vaquinha', 'praga', 'Soja', 'medio', 'Praga polífaga'],

            ['Ferrugem-comum', 'doenca', 'Milho', 'medio', 'Fungo que forma pústulas nas folhas'],
            ['Cercosporiose', 'doenca', 'Milho', 'medio', 'Mancha foliar que reduz área fotossintética'],
            ['Podridão-do-colmo', 'doenca', 'Milho', 'alto', 'Apodrecimento do colmo, risco de tombamento'],
            ['Ferrugem-asiática', 'doenca', 'Soja', 'alto', 'Principal doença da soja, perdas severas'],
            ['Mofo-branco', 'doenca', 'Soja', 'alto', 'Fungo que ataca em condições de alta umidade'],
            ['Oídio', 'doenca', 'Soja', 'medio', 'Pó branco nas folhas'],
            ['Antracnose', 'doenca', 'Soja', 'medio', 'Manchas necróticas em hastes e vagens'],

            ['Buva', 'planta_daninha', null, 'alto', 'Resistente a glifosato, difícil controle'],
            ['Capim-amargoso', 'planta_daninha', null, 'alto', 'Resistente a glifosato, forma touceiras'],
            ['Pé-de-galinha', 'planta_daninha', null, 'medio', 'Gramínea comum em bordaduras'],
            ['Caruru', 'planta_daninha', null, 'medio', 'Folha larga de crescimento rápido'],
            ['Corda-de-viola', 'planta_daninha', null, 'medio', 'Trepadeira que dificulta a colheita'],
            ['Trapoeraba', 'planta_daninha', null, 'medio', 'Tolerante a glifosato'],
            ['Tiguera (milho voluntário)', 'planta_daninha', null, 'medio', 'Milho da safra anterior que germina na soja'],

            ['Geada', 'clima', null, 'alto', 'Queima de tecidos por temperatura abaixo de zero'],
            ['Seca Prolongada', 'clima', null, 'alto', 'Déficit hídrico prolongado, estresse na cultura'],
            ['Granizo', 'clima', null, 'alto', 'Dano mecânico direto às plantas'],
            ['Excesso de Chuva', 'clima', null, 'medio', 'Encharcamento do solo, risco de doenças fúngicas'],
        ];

        foreach ($pragas as [$nome, $tipo, $cultura, $nivelRisco, $descricao]) {
            Praga::firstOrCreate(['nome' => $nome], [
                'tipo' => $tipo,
                'cultura_id' => $cultura ? $culturas[$cultura] : null,
                'nivel_risco' => $nivelRisco,
                'descricao' => $descricao,
                'ativa' => true,
            ]);
        }
    }
}
