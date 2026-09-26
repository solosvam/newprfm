<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep any custom status names before removing the legacy column.
        DB::table('order_statuses')
            ->where(function ($query) {
                $query->whereNull('name_az')->orWhere('name_az', '');
            })
            ->whereNotNull('name')
            ->update(['name_az' => DB::raw('name')]);

        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        DB::table('order_statuses')->update(['name' => DB::raw('name_az')]);
    }
};
