<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membro_amigos', function (Blueprint $table) {
            $table->unsignedBigInteger('membro_id');
            $table->unsignedBigInteger('amigo_id');
            $table->timestamps();
            $table->primary(['membro_id','amigo_id']);
            $table->foreign('membro_id')->references('id')->on('membros')->onDelete('cascade');
            $table->foreign('amigo_id')->references('id')->on('membros')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membro_amigos');
    }
}; 