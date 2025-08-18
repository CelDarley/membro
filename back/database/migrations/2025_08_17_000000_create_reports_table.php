<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->longText('search_text');
            $table->string('row_hash', 64)->unique();
            $table->timestamps();

            $table->fullText('search_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
}; 