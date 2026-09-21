<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'brand_name', 'size_name']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->after('product_variant_id');
            $table->string('brand_name')->nullable()->after('product_name');
            $table->string('size_name')->nullable()->after('brand_name');
        });
    }
};
