<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->constrained('users');
            $table->text('reason');
            $table->unsignedInteger('provider_amount');
            $table->unsignedInteger('wallet_amount');
            $table->string('status');
            $table->string('provider_refund_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
