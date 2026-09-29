<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator CRM-də qiyməti dəyişəndə saytdakı qiymət burada qalır:
 * unit_price = satılan qiymət, list_price = sayt qiyməti (dəyişməyibsə null).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('list_price', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('list_price');
        });
    }
};
