<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncLegacyParfumTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('old_id')->nullable(), $t->string('name'), $t->unsignedBigInteger('type_id')]);
        DB::table('types')->insert([['id' => 1, 'name_az' => 'Parfum suyu'], ['id' => 5, 'name_az' => 'Digər']]);
        DB::table('products')->insert([
            ['id' => 1, 'old_id' => 100, 'name' => 'A', 'type_id' => 1],
            ['id' => 2, 'old_id' => 101, 'name' => 'B', 'type_id' => 5],
            ['id' => 3, 'old_id' => 102, 'name' => 'C', 'type_id' => 1],
        ]);
        Http::fake(['*migration-product.php*' => Http::response(['success' => true, 'product_ids' => [100, 101, 999]])]);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->artisan('parfumshop:sync-parfum-type')
            ->expectsOutputToContain('Yeni bazada old_id ilə tapılan: 2 (tapılmayan: 1)')
            ->expectsOutputToContain('Tipi dəyişəcək: 1')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('products')->where('id', 1)->value('type_id'));
        Http::assertSent(fn ($request) => $request['parfum_type'] === 'Parfum');
    }

    public function test_apply_moves_only_listed_products(): void
    {
        $this->artisan('parfumshop:sync-parfum-type --apply')->expectsOutputToContain('Yeniləndi: 1')->assertSuccessful();

        $this->assertSame([5, 5, 1], DB::table('products')->orderBy('id')->pluck('type_id')->map(fn ($v) => (int) $v)->all());
    }
}
