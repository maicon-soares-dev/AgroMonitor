<?php

namespace App\Services;

use App\Models\Monitoramento;
use App\Models\MonitoramentoFoto;
use App\Models\Plantio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Regras dos monitoramentos: cálculo do resultado do ponto (milho: % de
 * plantas com praga; soja: pragas/m² na batida de pano), nível de controle e
 * lista de itens (pragas, doenças, daninhas, clima) encontrados na visita.
 */
class MonitoramentoService
{
    /** Milho: acima de 10% das plantas com praga é crítico. */
    const LIMITE_MILHO = 10;

    /** Soja: a partir de 3 pragas/m² é crítico. */
    const LIMITE_SOJA = 3.0;

    /**
     * Índice de uma praga (antigo indicePraga, usado nos relatórios).
     * Milho: plantas com a praga ÷ plantas avaliadas × 100.
     * Soja: total ÷ divisor (metros / 2 somados dos pontos) = média por m².
     *
     * @return array{0: ?float, 1: ?string, 2: ?string} [índice, unidade, nível]
     */
    public static function indicePraga(?float $total, ?string $cultura, ?float $plantasAvaliadas, ?float $divisorSoja, int $casasSoja = 2): array
    {
        $cultura = self::culturaCalculo($cultura);
        if ($total === null) {
            return [null, null, null];
        }
        if ($cultura === 'milho' && $plantasAvaliadas > 0) {
            $indice = round($total / $plantasAvaliadas * 100, 1);

            return [$indice, '%', $indice > self::LIMITE_MILHO ? 'critico' : 'normal'];
        }
        if ($cultura === 'soja' && $divisorSoja > 0) {
            $indice = round($total / $divisorSoja, $casasSoja);

            return [$indice, '/m²', $indice >= self::LIMITE_SOJA ? 'critico' : 'normal'];
        }

        return [null, null, null];
    }

    /** "Milho", "MILHO " → "milho" (só milho e soja têm cálculo automático). */
    public static function culturaCalculo(?string $nome): ?string
    {
        $nome = Str::lower(Str::ascii(trim((string) $nome)));

        return in_array($nome, ['milho', 'soja'], true) ? $nome : null;
    }

    public function criar(array $dados): Monitoramento
    {
        return DB::transaction(function () use ($dados) {
            $monitoramento = Monitoramento::create($this->campos($dados));
            $this->salvarItens($monitoramento, $dados['itens'] ?? []);

            return $monitoramento;
        });
    }

    /** Atualiza o monitoramento; a lista de itens só é trocada se vier no corpo. */
    public function atualizar(Monitoramento $monitoramento, array $dados): void
    {
        DB::transaction(function () use ($monitoramento, $dados) {
            $monitoramento->update($this->campos($dados, $monitoramento));
            if (array_key_exists('itens', $dados)) {
                $this->salvarItens($monitoramento, $dados['itens']);
            }
        });
    }

    /** Apaga o monitoramento (itens e fotos vão por CASCADE) e os arquivos das fotos. */
    public function excluir(Monitoramento $monitoramento): void
    {
        $arquivos = $monitoramento->fotos()->pluck('arquivo');
        $monitoramento->delete();
        Storage::disk('public')->delete($arquivos->map(fn ($a) => MonitoramentoFoto::PASTA.'/'.$a)->all());
    }

    /** Grava o arquivo no disco "public" e registra a foto. */
    public function adicionarFoto(string $monitoramentoId, UploadedFile $arquivo, ?string $descricao): MonitoramentoFoto
    {
        $nome = 'mon'.$monitoramentoId.'_'.uniqid().'.'.$arquivo->extension();
        $arquivo->storeAs(MonitoramentoFoto::PASTA, $nome, 'public');

        return MonitoramentoFoto::create([
            'monitoramento_id' => $monitoramentoId,
            'arquivo' => $nome,
            'descricao' => $descricao,
        ]);
    }

    public function excluirFoto(MonitoramentoFoto $foto): void
    {
        $foto->delete();
        Storage::disk('public')->delete(MonitoramentoFoto::PASTA.'/'.$foto->arquivo);
    }

    /** Substitui os itens da visita; itens sem nome são ignorados. */
    public function salvarItens(Monitoramento $monitoramento, ?array $itens): void
    {
        $monitoramento->itens()->delete();

        foreach ($itens ?? [] as $item) {
            if (empty($item['nome'])) {
                continue;
            }
            $monitoramento->itens()->create([
                'tipo' => ($item['tipo'] ?? null) ?: 'praga',
                'nome' => $item['nome'],
                'quantidade' => $item['quantidade'] ?? null,
                'severidade' => $item['severidade'] ?? null,
                'latitude' => $item['latitude'] ?? null,
                'longitude' => $item['longitude'] ?? null,
                'observacoes' => $item['observacoes'] ?? null,
            ]);
        }
    }

    /**
     * Campos do monitoramento com o resultado calculado aqui para milho e
     * soja (mesma fórmula da tela, 1 casa decimal). Nas demais culturas vale
     * o que o front mandou. Na edição, campo que não veio mantém o valor gravado.
     */
    private function campos(array $dados, ?Monitoramento $atual = null): array
    {
        $campos = collect($dados)->except('itens')->all();
        $dados = $campos + ($atual?->attributesToArray() ?? []);
        $cultura = Plantio::with('cultura:id,nome')->find($dados['plantio_id'])?->cultura?->nome;

        $calculado = match (self::culturaCalculo($cultura)) {
            'milho' => self::indicePraga(
                $dados['milho_plantas_praga'] ?? 0,
                'milho',
                $dados['milho_plantas_avaliadas'] ?? 20,
                null
            ),
            'soja' => self::indicePraga(
                $dados['soja_pragas_encontradas'] ?? 0,
                'soja',
                null,
                ($dados['soja_metros_lineares'] ?? 2) / 2,
                1
            ),
            default => null,
        };

        if ($calculado && $calculado[0] !== null) {
            [$campos['resultado_final'], $campos['unidade_resultado'], $campos['nivel_controle']] = $calculado;
        }

        return $campos;
    }
}
