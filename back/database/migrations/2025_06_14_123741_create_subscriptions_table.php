<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->enum('billing_cycle', ['monthly', 'semiannual', 'annual']);
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['active', 'inactive', 'cancelled', 'expired'])->default('active');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable(); // ID do Mercado Pago
            $table->json('payment_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
