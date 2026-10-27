<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Movimentos que fecham o ciclo: colheita (1 por plantio), recomendação,
// aplicação e o histórico de relatórios gerados.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colheitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantio_id')->unique('uk_colheita_plantio')
                ->constrained('plantios')->cascadeOnDelete();
            $table->date('data_colheita');
            $table->decimal('produtividade_sc_ha', 8, 2)->comment('sacas por hectare');
            $table->decimal('umidade_pct', 5, 2)->nullable()->comment('umidade do grão na colheita (%)');
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('recomendacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('monitoramento_id')->constrained('monitoramentos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->comment('agrônomo responsável')
                ->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('aplicacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('talhao_id')->nullable()->constrained('talhoes')->nullOnDelete();
            $table->foreignId('plantio_id')->nullable()->constrained('plantios')->nullOnDelete();
            $table->foreignUuid('monitoramento_id')->nullable()
                ->comment('monitoramento que motivou a aplicação, se houver')
                ->constrained('monitoramentos')->nullOnDelete();
            $table->foreignId('recomendacao_id')->nullable()->constrained('recomendacoes')->nullOnDelete();
            $table->string('produto', 150);
            $table->string('tipo_produto', 30)->nullable()->default('defensivo')->comment('defensivo | adubo | corretivo');
            $table->decimal('dose', 10, 2)->nullable();
            $table->string('unidade_dose', 20)->nullable();
            $table->date('data_aplicacao');
            $table->foreignId('usuario_id')->nullable()->comment('responsável pela aplicação')
                ->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('data_aplicacao');
        });

        Schema::create('relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('safra_id')->nullable()->constrained('safras')->nullOnDelete();
            $table->foreignId('plantio_id')->nullable()->constrained('plantios')->nullOnDelete();
            $table->string('tipo', 30)->nullable()->default('safra')->comment('safra | plantio | individual');
            $table->string('arquivo_path')->nullable();
            $table->foreignId('gerado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('gerado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios');
        Schema::dropIfExists('aplicacoes');
        Schema::dropIfExists('recomendacoes');
        Schema::dropIfExists('colheitas');
    }
};
