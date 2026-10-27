<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Área = cadastro fixo (imóvel físico do cliente). Não tem safra nem cultura
// própria — essas variam por plantio. Talhão = divisão física fixa da área.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('nome', 150);
            $table->decimal('hectares', 10, 2);
            $table->decimal('alqueires', 10, 2)->storedAs('hectares / 2.42');
            $table->integer('qtd_pontos')->default(5);
            $table->text('observacoes')->nullable();
            $table->longText('geometria')->nullable()->comment('GeoJSON do contorno');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('talhoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('variedade', 100)->nullable();
            $table->string('tipo', 20)->nullable()->default('producao');
            $table->decimal('hectares', 8, 2)->nullable();
            $table->string('cor_hex', 7)->nullable()->default('#4caf50');
            $table->longText('geometria')->nullable()->comment('GeoJSON do contorno');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talhoes');
        Schema::dropIfExists('areas');
    }
};
