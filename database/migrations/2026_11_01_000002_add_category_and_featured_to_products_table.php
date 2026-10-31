<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SQLite cannot drop a column that an index still covers.
            $table->dropIndex(['is_featured']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('is_featured');
        });
    }
};
