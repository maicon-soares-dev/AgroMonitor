<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome', 150);
            $table->string('documento', 20)->nullable()->comment('CPF ou CNPJ');
            $table->string('telefone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('ativo')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
