<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            // Existing published reviews stay visible; new reviews require approval.
            $table->boolean('active')->default(false)->after('comment');
        });

        // Preserve reviews that were publicly visible before moderation was introduced.
        \Illuminate\Support\Facades\DB::table('product_reviews')->update(['active' => 1]);
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
