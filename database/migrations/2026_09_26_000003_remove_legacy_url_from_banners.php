<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve all legacy image filenames before dropping the old column.
        // Do not overwrite AZ images already uploaded through the multilingual editor.
        DB::table('banners')
            ->where(function ($query) {
                $query->whereNull('url_az')->orWhere('url_az', '');
            })
            ->whereNotNull('url')
            ->where('url', '<>', '')
            ->update(['url_az' => DB::raw('url')]);

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('url')->nullable();
        });

        DB::table('banners')->update(['url' => DB::raw('url_az')]);
    }
};
