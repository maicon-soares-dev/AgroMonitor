<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cadeia de monitoramento: pontos da área → monitoramentos → itens e fotos.
// Monitoramentos, itens e fotos usam UUID, pensando na sincronização offline.
return new class extends Migration
{
    public function up(): void
    {
        // Pontos ficam presos à ÁREA (posição física fixa no terreno), não ao
        // plantio — são reaproveitados safra após safra.
        Schema::create('pontos_monitoramento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('talhao_id')->nullable()->constrained('talhoes')->nullOnDelete();
            $table->integer('numero_ponto');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('data_registro');
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['area_id', 'numero_ponto'], 'uk_area_ponto');
        });

        Schema::create('monitoramentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('ponto_id')->constrained('pontos_monitoramento')->cascadeOnDelete();
            $table->foreignId('plantio_id')->constrained('plantios')->cascadeOnDelete();
            $table->date('data_monitoramento');
            $table->string('estagio_fenologico', 20)->nullable()
                ->comment('texto livre; o app sugere VE/V4/V6/R1... conforme a cultura do plantio');
            $table->integer('milho_plantas_avaliadas')->nullable();
            $table->integer('milho_plantas_praga')->nullable();
            $table->decimal('milho_percentual', 5, 2)->nullable();
            $table->integer('soja_pragas_encontradas')->nullable();
            $table->decimal('soja_metros_lineares', 4, 1)->nullable();
            $table->decimal('soja_pragas_por_m2', 6, 2)->nullable();
            $table->decimal('resultado_final', 8, 2)->nullable();
            $table->string('unidade_resultado', 20)->nullable();
            $table->string('nivel_controle', 20)->nullable();
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('data_monitoramento');
            $table->index('nivel_controle');
        });

        // Cada praga/doença/planta daninha/condição climática encontrada.
        Schema::create('monitoramento_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitoramento_id')->constrained('monitoramentos')->cascadeOnDelete();
            $table->string('tipo', 20)->default('praga')->comment('praga | doenca | planta_daninha | clima');
            $table->string('nome', 150);
            $table->integer('quantidade')->nullable()->comment('contagem, quando aplicável (pragas)');
            $table->string('severidade', 20)->nullable()->comment('leve | moderada | severa');
            $table->decimal('latitude', 10, 8)->nullable()->comment('sobrescreve a coordenada do ponto');
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('tipo');
        });

        Schema::create('monitoramento_fotos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitoramento_id')->constrained('monitoramentos')->cascadeOnDelete();
            $table->string('arquivo');
            $table->string('descricao')->nullable();
            $table->boolean('favorita')->default(false)->comment('foto escolhida pelo agrônomo para o relatório');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoramento_fotos');
        Schema::dropIfExists('monitoramento_itens');
        Schema::dropIfExists('monitoramentos');
        Schema::dropIfExists('pontos_monitoramento');
    }
};
