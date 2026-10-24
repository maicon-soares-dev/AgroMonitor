<?php

namespace App\Services;

use App\Models\Area;
use Illuminate\Support\Facades\DB;

/**
 * Regras do cadastro de áreas, incluindo a geração automática dos pontos de
 * monitoramento (requisito "geração de dados em tabelas").
 */
class AreaService
{
    public function __construct(private TalhaoService $talhoes)
    {
    }

    /**
     * Cria a área, os pontos de monitoramento (1..qtd_pontos, usando as
     * coordenadas que o front calculou dentro do polígono, se vierem) e os
     * talhões desenhados no cadastro. Tudo numa transação.
     */
    public function criar(array $dados): Area
    {
        return DB::transaction(function () use ($dados) {
            $area = Area::create([
                'cliente_id' => $dados['cliente_id'],
                'nome' => $dados['nome'],
                'hectares' => $dados['hectares'],
                'qtd_pontos' => $dados['qtd_pontos'] ?? 5,
                'observacoes' => $dados['observacoes'] ?? null,
                'geometria' => isset($dados['geometria']) ? json_encode($dados['geometria']) : null,
            ]);

            $this->gerarPontos($area, $area->qtd_pontos, $dados['pontos_coords'] ?? []);

            foreach ($dados['talhoes'] ?? [] as $talhao) {
                $this->talhoes->inserir($area->id, $talhao);
            }

            return $area;
        });
    }

    /**
     * Atualiza a área. A geometria só muda se vier no corpo (a tela de edição
     * não manda o contorno). Se a quantidade de pontos aumentar, cria só os
     * que faltam; nunca apaga pontos, para não perder o histórico de
     * monitoramento (FK ON DELETE CASCADE).
     */
    public function atualizar(Area $area, array $dados): void
    {
        DB::transaction(function () use ($area, $dados) {
            $campos = [
                'cliente_id' => $dados['cliente_id'],
                'nome' => $dados['nome'],
                'hectares' => $dados['hectares'],
                'qtd_pontos' => $dados['qtd_pontos'] ?? 5,
                'observacoes' => $dados['observacoes'] ?? null,
            ];
            if (array_key_exists('geometria', $dados)) {
                $campos['geometria'] = $dados['geometria'] !== null ? json_encode($dados['geometria']) : null;
            }
            $area->update($campos);

            $existentes = $area->pontos()->count();
            $faltam = $area->qtd_pontos - $existentes;
            if ($faltam > 0) {
                $ultimoNumero = (int) $area->pontos()->max('numero_ponto');
                $this->gerarPontos($area, $faltam, [], $ultimoNumero + 1);
            }
        });
    }

    /**
     * Gera $quantidade pontos numerados a partir de $primeiroNumero.
     * $coordenadas = [[lat, lng], ...] na mesma ordem dos pontos.
     */
    public function gerarPontos(Area $area, int $quantidade, array $coordenadas = [], int $primeiroNumero = 1): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            $coordenada = $coordenadas[$i] ?? null;
            $area->pontos()->create([
                'numero_ponto' => $primeiroNumero + $i,
                'latitude' => $coordenada[0] ?? null,
                'longitude' => $coordenada[1] ?? null,
                'data_registro' => now()->toDateString(),
            ]);
        }
    }
}
