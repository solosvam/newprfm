<?php

namespace Tests\Feature\Catalog;

use App\Services\CatalogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarProductsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->integer('brand_id')->nullable(), $t->boolean('active')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')->nullable(), $t->decimal('price'), $t->boolean('active')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->integer('order_status_id'), $t->timestamps()]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->integer('product_id'), $t->integer('quantity'), $t->integer('cancelled_quantity')->default(0)]);
        DB::table('order_statuses')->insert([['id' => 1, 'code' => 'delivered'], ['id' => 2, 'code' => 'cancelled']]);
        foreach (range(1, 12) as $i) {
            DB::table('products')->insert(['id' => $i, 'name' => 'P'.$i, 'slug' => 'p'.$i, 'active' => $i !== 12]);
            DB::table('product_variants')->insert(['product_id' => $i, 'price' => 10 + $i, 'active' => 1]);
            DB::table('product_images')->insert(['product_id' => $i, 'image' => 'p'.$i.'.jpg']);
        }
    }

    private function sold(int $product, int $qty, int $status = 1, int $cancelled = 0): void
    {
        $order = DB::table('orders')->insertGetId(['order_status_id' => $status, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $order, 'product_id' => $product, 'quantity' => $qty, 'cancelled_quantity' => $cancelled]);
    }

    private function sidebar(): array
    {
        return (new \ReflectionMethod(CatalogService::class, 'sidebarProducts'))->invoke(app(CatalogService::class));
    }

    public function test_best_sellers_by_sold_quantity_and_lists_do_not_overlap(): void
    {
        $this->sold(3, 5);
        $this->sold(7, 9);
        $this->sold(5, 20, 2);          // ləğv olunmuş sifariş sayılmır
        $this->sold(9, 4, 1, 4);        // tam ləğv olunan miqdar sayılmır
        $this->sold(12, 50);            // deaktiv məhsul
        $s = $this->sidebar();
        $best = $s['bestSellers']->pluck('id')->all();
        $this->assertSame([7, 3], array_slice($best, 0, 2));
        $this->assertCount(5, $best);   // ən yenilərlə tamamlanır
        $this->assertNotContains(12, $best);
        $this->assertNotContains(5, array_slice($best, 0, 2));
        $this->assertEmpty(array_intersect($best, $s['recommendedProducts']->pluck('id')->all()));
        $this->assertCount(5, $s['recommendedProducts']);
    }

    public function test_product_deactivated_after_cache_is_hidden(): void
    {
        $this->sold(3, 5);
        $this->sidebar();
        DB::table('products')->where('id', 3)->update(['active' => 0]);
        $this->assertNotContains(3, $this->sidebar()['bestSellers']->pluck('id')->all());
    }
}
