<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Plantio = um ciclo de cultivo (uma cultura, numa safra, numa área). A
// variedade pode ser única (plantios.variedade_id) ou definida por talhão
// (plantio_talhoes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('talhao_id')->nullable()->constrained('talhoes')->nullOnDelete();
            $table->foreignId('safra_id')->constrained('safras')->restrictOnDelete();
            $table->date('data_plantio');
            $table->foreignId('cultura_id')->constrained('culturas')->restrictOnDelete();
            $table->foreignId('variedade_id')->nullable()->constrained('variedades')->nullOnDelete();
            $table->decimal('populacao_semente', 8, 2)->nullable();
            $table->foreignId('adubo_id')->nullable()->constrained('adubos')->nullOnDelete();
            $table->decimal('adubo_qtd_kg', 8, 2)->nullable();
            $table->string('condicao_plantio', 20)->nullable()->default('apos_chuva');
            $table->string('condicao_solo', 20)->nullable()->default('boa');
            $table->text('observacoes')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('data_plantio');
        });

        Schema::create('plantio_talhoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantio_id')->constrained('plantios')->cascadeOnDelete();
            $table->foreignId('talhao_id')->constrained('talhoes')->cascadeOnDelete();
            $table->foreignId('variedade_id')->nullable()->constrained('variedades')->nullOnDelete();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['plantio_id', 'talhao_id'], 'uk_plantio_talhao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantio_talhoes');
        Schema::dropIfExists('plantios');
    }
};
