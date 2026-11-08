<?php

use App\Models\StoreSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('signup_credit');
            $table->timestamps();
        });

        // The one row, starting at the default credit ($20).
        DB::table('store_settings')->insert([
            'signup_credit' => StoreSettings::DEFAULT_SIGNUP_CREDIT_CENTS,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
