<?php

use App\Models\Review;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ratings', 'reviews');

        Schema::table('reviews', function (Blueprint $table) {
            $table->text('body')->nullable()->after('stars');
            $table->timestamp('hidden_at')->nullable()->index()->after('body');
        });

        // Only buyers may review now. Ratings from people with no placed
        // order holding the product are deleted, and can't be brought back.
        Review::deleteIneligible();
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['hidden_at']);
            $table->dropColumn(['body', 'hidden_at']);
        });

        Schema::rename('reviews', 'ratings');
    }
};
