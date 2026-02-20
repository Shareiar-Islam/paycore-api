<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('type');
            $table->string('status')->default('created');
            $table->string('external_id')->nullable()->index();
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('customer_email')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['merchant_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
