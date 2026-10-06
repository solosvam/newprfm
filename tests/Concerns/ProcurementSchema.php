<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Təminat testləri üçün in-memory sqlite sxemi (developer bazasına toxunmur). */
trait ProcurementSchema
{
    protected function procurementSchema(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
        });
        Schema::create('order_statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name_az')->nullable();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->integer('customer_id');
            $t->unsignedBigInteger('order_status_id');
            $t->string('order_no')->default('TEST');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->integer('quantity');
            $t->integer('product_id')->nullable();
            $t->integer('product_variant_id')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('brand_id')->nullable();
            $t->integer('type_id')->nullable();
        });
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('types', function (Blueprint $t) {
            $t->id();
            $t->string('name_az');
        });
        Schema::create('genders', function (Blueprint $t) {
            $t->id();
            $t->string('name_az');
        });
        Schema::create('product_genders', function (Blueprint $t) {
            $t->integer('product_id');
            $t->integer('gender_id');
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->integer('size_id')->nullable();
        });
        Schema::create('sizes', function (Blueprint $t) {
            $t->id();
            $t->string('name_az');
        });
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->up();
        (require database_path('migrations/2026_09_29_160000_create_order_item_cancellations_table.php'))->up();
        (require database_path('migrations/2026_10_06_203155_add_fee_type_to_order_item_cancellations.php'))->up();
        (require database_path('migrations/2026_09_29_190000_add_supply_flow_to_order_item_allocations.php'))->up();
        (require database_path('migrations/2026_09_29_180000_create_warehouse_access_links.php'))->up();
        (require database_path('migrations/2026_09_21_150000_create_sms_templates_table.php'))->up();
        (require database_path('migrations/2026_09_30_100000_create_sms_logs_and_warehouse_sms.php'))->up();
    }
}
