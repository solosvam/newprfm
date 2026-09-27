<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreignId('customer_address_id')->nullable()->change();
            $table->string('guest_mobile', 12)->nullable()->index();
            $table->boolean('one_click')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['guest_mobile']);
            $table->dropIndex(['one_click']);
            $table->dropColumn(['guest_mobile', 'one_click']);
        });
        // Existing guest orders must be assigned before restoring NOT NULL constraints.
    }
};
