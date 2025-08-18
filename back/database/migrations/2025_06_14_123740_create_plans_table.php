<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('monthly_price', 8, 2);
            $table->decimal('semiannual_price', 8, 2);
            $table->decimal('annual_price', 8, 2);
            $table->integer('seals_limit')->nullable(); // null = ilimitado
            $table->integer('contracts_limit')->nullable(); // null = ilimitado
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
