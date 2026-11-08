<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Not dropConstrainedForeignId: on SQLite it leaves the unique index behind.
            $table->dropForeign(['refund_id']);
            $table->dropUnique(['refund_id']);
            $table->dropColumn('refund_id');
        });
    }
};
