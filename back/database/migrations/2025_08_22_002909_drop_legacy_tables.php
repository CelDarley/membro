<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Desabilita checks para evitar erro de chave estrangeira
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Listas de tabelas legadas para remoção
        $tables = [
            'subscriptions',
            'plans',
            'faqs',
            'testimonials',
            'selos',
            'contrato_tipos',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Down vazio: não recriamos as tabelas legadas
    }
};
