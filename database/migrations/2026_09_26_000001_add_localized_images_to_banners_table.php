<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('url_az')->nullable();
            $table->string('url_en')->nullable();
            $table->string('url_ru')->nullable();
        });

        // Existing banners remain visible in every language until translated images are uploaded.
        DB::table('banners')->whereNotNull('url')->update(['url_az' => DB::raw('url')]);
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['url_az', 'url_en', 'url_ru']);
        });
    }
};
