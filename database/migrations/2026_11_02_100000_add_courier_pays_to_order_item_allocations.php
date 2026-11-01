<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuryer təyin olunanda hər anbar üçün seçim: kuryer anbara pul verir (köhnə qayda) və ya vermir —
 * "pul vermə"də kuryer yalnız "Götürdüm" basır, anbara borc qalır (şirkət sonra özü ödəyir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_allocations', function (Blueprint $table) {
            $table->boolean('courier_pays')->default(true)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_allocations', fn (Blueprint $table) => $table->dropColumn('courier_pays'));
    }
};
