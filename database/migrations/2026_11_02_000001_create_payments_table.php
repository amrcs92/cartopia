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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->string('status');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('usd');
            $table->string('provider_reference')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['method', 'provider_reference']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
