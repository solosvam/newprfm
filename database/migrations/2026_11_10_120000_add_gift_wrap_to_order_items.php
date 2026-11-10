<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hədiyyəlik bükülmə məhsul sətri üzrə: gift_wrap — bu sətrin bütün ədədləri bükülür,
 * gift_wrap_fee — sifariş anındakı bir ədədin bükülmə haqqı (pulsuz rejimdə 0).
 * orders.gift_wrap / gift_wrap_fee qalır: ən azı bir sətir bükülürsə true, haqq — sətirlərin cəmi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('gift_wrap')->default(false)->after('total');
            $table->decimal('gift_wrap_fee', 10, 2)->default(0)->after('gift_wrap');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['gift_wrap', 'gift_wrap_fee']);
        });
    }
};
