<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catálogos: safras, culturas, variedades, adubos e pragas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50)->unique()->comment('texto livre, ex: "Safra 2026/2027"');
            $table->tinyInteger('mes_inicio')->default(1);
            $table->integer('ano_inicio');
            $table->tinyInteger('mes_fim')->default(12);
            $table->integer('ano_fim');
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('ativa');
        });

        Schema::create('culturas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50)->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('ativa');
        });

        Schema::create('variedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultura_id')->constrained('culturas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['cultura_id', 'nome'], 'uk_cultura_variedade');
            $table->index('ativa');
        });

        Schema::create('adubos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->string('tipo', 50)->nullable();
            $table->string('npk', 20)->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('ativa');
        });

        Schema::create('pragas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->string('tipo', 20)->default('praga')->comment('praga | doenca | planta_daninha | clima');
            $table->foreignId('cultura_id')->nullable()
                ->comment('NULL = aplica-se a qualquer cultura (ex: eventos climáticos)')
                ->constrained('culturas')->nullOnDelete();
            $table->string('nivel_risco', 20)->nullable()->default('medio');
            $table->text('descricao')->nullable();
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('ativa');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pragas');
        Schema::dropIfExists('adubos');
        Schema::dropIfExists('variedades');
        Schema::dropIfExists('culturas');
        Schema::dropIfExists('safras');
    }
};
