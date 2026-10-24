<?php

namespace App\Services;

use App\Models\Plantio;
use App\Models\PlantioTalhao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regras dos plantios: variedade única (plantios.variedade_id) ou uma
 * variedade por talhão (plantio_talhoes).
 */
class PlantioService
{
    /**
     * Expressão SQL do nome da(s) variedade(s) de um plantio (antigo
     * sqlVariedadeNome): a variedade única ou, se o plantio foi dividido por
     * talhões, as variedades dos talhões (ex.: "M6211, NK7059").
     * Fica como expressão porque é usada dentro de várias consultas.
     */
    public static function sqlVariedadeNome(string $aliasPlantio = 'plantios', string $aliasVariedade = 'variedades'): string
    {
        return "COALESCE({$aliasVariedade}.nome, (SELECT GROUP_CONCAT(DISTINCT vt.nome ORDER BY vt.nome SEPARATOR ', ')
                  FROM plantio_talhoes ptv JOIN variedades vt ON vt.id = ptv.variedade_id
                  WHERE ptv.plantio_id = {$aliasPlantio}.id))";
    }

    /** Cria o plantio e, se vierem, as variedades por talhão. */
    public function criar(array $dados): Plantio
    {
        return DB::transaction(function () use ($dados) {
            $plantio = Plantio::create($this->campos($dados));
            if (array_key_exists('talhoes', $dados)) {
                $this->salvarTalhoes($plantio, $dados['talhoes']);
            }

            return $plantio;
        });
    }

    /** Atualiza o plantio; a lista de talhões só é trocada se vier no corpo. */
    public function atualizar(Plantio $plantio, array $dados): void
    {
        DB::transaction(function () use ($plantio, $dados) {
            $plantio->update($this->campos($dados));
            if (array_key_exists('talhoes', $dados)) {
                $this->salvarTalhoes($plantio, $dados['talhoes']);
            }
        });
    }

    /**
     * Grava a variedade de cada talhão no plantio, substituindo a lista
     * inteira (antigo salvarTalhoesPlantio). Lista vazia/nula = plantio sem
     * divisão por talhão.
     */
    public function salvarTalhoes(Plantio $plantio, ?array $talhoes): void
    {
        $plantio->plantioTalhoes()->delete();

        foreach ($talhoes ?? [] as $talhao) {
            if (empty($talhao['talhao_id'])) {
                continue;
            }
            $plantio->plantioTalhoes()->create([
                'talhao_id' => $talhao['talhao_id'],
                'variedade_id' => ($talhao['variedade_id'] ?? null) ?: null,
            ]);
        }
    }

    /** Talhões do plantio com contorno e nome da variedade (usado nos relatórios). */
    public function talhoesDoPlantio(int $plantioId): Collection
    {
        return PlantioTalhao::query()
            ->join('talhoes as t', 't.id', '=', 'plantio_talhoes.talhao_id')
            ->leftJoin('variedades as v', 'v.id', '=', 'plantio_talhoes.variedade_id')
            ->where('plantio_talhoes.plantio_id', $plantioId)
            ->orderBy('t.nome')
            ->orderBy('t.id')
            ->get(['t.id', 't.nome', 't.cor_hex', 't.hectares', 't.geometria', 'v.nome as variedade_nome']);
    }

    /** Padrões da API antiga para os campos opcionais. */
    private function campos(array $dados): array
    {
        return [
            'area_id' => $dados['area_id'],
            'talhao_id' => $dados['talhao_id'] ?? null,
            'safra_id' => $dados['safra_id'],
            'data_plantio' => $dados['data_plantio'],
            'cultura_id' => $dados['cultura_id'],
            'variedade_id' => $dados['variedade_id'] ?? null,
            'populacao_semente' => $dados['populacao_semente'] ?? null,
            'adubo_id' => $dados['adubo_id'] ?? null,
            'adubo_qtd_kg' => $dados['adubo_qtd_kg'] ?? null,
            'condicao_plantio' => ($dados['condicao_plantio'] ?? null) ?: 'apos_chuva',
            'condicao_solo' => ($dados['condicao_solo'] ?? null) ?: 'boa',
            'observacoes' => $dados['observacoes'] ?? null,
        ];
    }
}
