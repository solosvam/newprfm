<?php

namespace Tests\Feature\Procurement;

use App\Services\Procurement\LastPurchase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LastPurchaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('warehouses', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->integer('product_variant_id')]);
        Schema::create('order_item_allocations', fn (Blueprint $t) => [$t->id(), $t->integer('order_item_id'), $t->integer('warehouse_id'),
            $t->decimal('unit_cost', 12, 2), $t->string('status'), $t->timestamps()]);
        Schema::create('allocation_status_logs', fn (Blueprint $t) => [$t->id(), $t->integer('order_item_allocation_id'), $t->string('to_status'), $t->timestamp('created_at')]);
    }

    public function test_latest_picked_allocation_per_variant(): void
    {
        DB::table('warehouses')->insert([['id' => 1, 'name_az' => 'Anbar A'], ['id' => 2, 'name_az' => 'Anbar B']]);
        DB::table('order_items')->insert([['id' => 1, 'product_variant_id' => 10], ['id' => 2, 'product_variant_id' => 10], ['id' => 3, 'product_variant_id' => 10], ['id' => 4, 'product_variant_id' => 20]]);
        $allocation = fn ($id, $item, $wh, $cost, $status, $at) => DB::table('order_item_allocations')->insert(
            ['id' => $id, 'order_item_id' => $item, 'warehouse_id' => $wh, 'unit_cost' => $cost, 'status' => $status, 'created_at' => $at, 'updated_at' => $at]);
        $allocation(1, 1, 1, 180, 'picked', '2026-10-01 10:00:00');
        $allocation(2, 2, 2, 195, 'picked', '2026-10-02 10:00:00');   // sonra seçilib, amma əvvəl götürülüb
        $allocation(3, 3, 1, 150, 'cancelled', '2026-10-20 10:00:00'); // ləğv — alış deyil
        DB::table('allocation_status_logs')->insert([
            ['order_item_allocation_id' => 1, 'to_status' => 'picked', 'created_at' => '2026-10-12 09:00:00'],
            ['order_item_allocation_id' => 2, 'to_status' => 'picked', 'created_at' => '2026-10-05 09:00:00'],
        ]);

        $result = LastPurchase::forVariants([10, 20]);
        $this->assertSame(['cost' => 180.0, 'warehouse' => 'Anbar A', 'date' => '12.10.2026'], $result[10]);
        $this->assertArrayNotHasKey(20, $result);
        $this->assertSame([], LastPurchase::forVariants([]));
    }
}
