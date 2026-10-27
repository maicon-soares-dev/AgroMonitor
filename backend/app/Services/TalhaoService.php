<?php

namespace App\Services;

use App\Models\Talhao;

class TalhaoService
{
    /**
     * Cria um talhão na área (usado no cadastro da área e no editor do mapa).
     * Equivale ao inserirTalhao() da API antiga: nome vazio vira "Talhão",
     * cor vazia vira verde e a geometria (GeoJSON) é gravada como texto JSON.
     */
    public function inserir(int $areaId, array $dados): Talhao
    {
        $nome = trim($dados['nome'] ?? '');

        return Talhao::create([
            'area_id' => $areaId,
            'nome' => $nome !== '' ? $nome : 'Talhão',
            'tipo' => 'producao',
            'hectares' => $dados['hectares'] ?? null,
            'cor_hex' => ($dados['cor_hex'] ?? null) ?: '#4caf50',
            'geometria' => isset($dados['geometria']) ? json_encode($dados['geometria']) : null,
        ]);
    }
}
