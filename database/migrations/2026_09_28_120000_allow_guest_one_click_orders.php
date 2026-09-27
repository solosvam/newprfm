<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preserve the actual existing FK column types (they may not be BIGINT UNSIGNED).
        foreach (['customer_id', 'customer_address_id'] as $column) {
            $definition = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['orders', $column]
            );
            if (!$definition) { throw new \RuntimeException("Missing orders.{$column}"); }
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE orders MODIFY `{$column}` {$definition->COLUMN_TYPE} NULL");
        }
        Schema::table('orders', function (Blueprint $table) {
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
