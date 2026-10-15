<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Müştəri ilə əlaqə saxlayın və yeri dəqiqləşdirin": müştəri hərəkətdədir, kuryer zəng edib yeri öyrənir.
 *  - customer_addresses.on_call — hər müştəri üçün bir xüsusi ünvan (bir dəfə yaranır, sonra təkrar işlədilir);
 *    saytda "Ünvanlarım"da və checkout-da göstərilmir, redaktə olunmur;
 *  - orders.meeting_place — həmin sifariş üçün görüş yeri (kuryer zəngdən sonra və ya operator yazır).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->boolean('on_call')->default(false)->after('customer_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('meeting_place', 500)->nullable()->after('customer_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('meeting_place'));
        Schema::table('customer_addresses', fn (Blueprint $table) => $table->dropColumn('on_call'));
    }
};
