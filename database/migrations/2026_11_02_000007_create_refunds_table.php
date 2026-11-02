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
            // A refund goes with its order, as the rest of a deleted account does.
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            // Null once the admin who refunded is deleted; the refund stays.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
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
