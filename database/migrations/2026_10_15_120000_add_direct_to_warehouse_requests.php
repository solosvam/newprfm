<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Anbar seç": operator ətirin anbarda olduğunu və qiymətini artıq bilir — sorğu göndərilmir, cavab gözlənilmir.
 * Sorğu və cavab tarixçə üçün yenə yazılır, amma "direct" işarəsi ilə: SMS getmir və SMS xətası sayılmır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_requests', function (Blueprint $table) {
            $table->boolean('direct')->default(false)->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_requests', function (Blueprint $table) {
            $table->dropColumn('direct');
        });
    }
};
