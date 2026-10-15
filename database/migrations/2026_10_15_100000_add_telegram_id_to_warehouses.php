<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anbarın Telegram çat id-si (istəyə bağlı). Rəqəmdir, qrupda mənfi olur (-100…) — ona görə mətn kimi saxlanır. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('telegram_id', 32)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('telegram_id');
        });
    }
};
