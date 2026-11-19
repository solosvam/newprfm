<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Köhnə saytın (old.parfumshop.az) təsdiqlənmiş rəyləri (parfumshop:import-reviews):
 *  - customer_id boş ola bilər — köhnə rəyi qonaq da yazırdı, yalnız adı məlumdur (author_name);
 *  - legacy_key — köhnə rəyin açarı (köhnə məhsul ID + ad + tarix + mətn), təkrar köçürmədə ikiqat düşməsin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });
        if (!Schema::hasColumn('product_reviews', 'author_name')) {
            Schema::table('product_reviews', function (Blueprint $table) {
                $table->string('author_name', 100)->nullable()->after('customer_id');
                $table->char('legacy_key', 40)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropUnique(['legacy_key']);
            $table->dropColumn(['author_name', 'legacy_key']);
        });
    }
};
