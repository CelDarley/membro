<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membros', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sexo')->nullable();
            $table->string('concurso')->nullable();
            $table->string('cargo_efetivo')->nullable();
            $table->string('titularidade')->nullable();
            $table->string('email_pessoal')->nullable();
            $table->string('cargo_especial')->nullable();
            $table->string('telefone_unidade')->nullable();
            $table->string('telefone_celular')->nullable();
            $table->string('unidade_lotacao')->nullable();
            $table->string('comarca_lotacao')->nullable();
            $table->text('time_extraprofissionais')->nullable();
            $table->unsignedInteger('quantidade_filhos')->nullable();
            $table->text('nomes_filhos')->nullable();
            $table->string('estado_origem', 2)->nullable();
            $table->text('academico')->nullable();
            $table->text('pretensao_carreira')->nullable();
            $table->text('carreira_anterior')->nullable();
            $table->text('lideranca')->nullable();
            $table->text('grupos_identitarios')->nullable();
            $table->timestamps();
            $table->index(['nome']);
            $table->index(['comarca_lotacao']);
            $table->index(['cargo_efetivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membros');
    }
}; 