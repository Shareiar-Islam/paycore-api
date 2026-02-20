<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_provider_credentials');
    }
};
