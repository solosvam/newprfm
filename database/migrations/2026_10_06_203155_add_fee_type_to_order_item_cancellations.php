<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sifarişin tam ləğvi: məhsullardan başqa çatdırılma və qablaşdırma haqqı da ayrıca ləğv qeydi olur
 * (order_item_id boş, fee_type: delivery | gift_wrap) — karta qaytarma ödənişin həmin sətrinə bağlansın.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
            $table->string('fee_type', 20)->nullable()->after('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->dropColumn('fee_type');
        });
    }
};
