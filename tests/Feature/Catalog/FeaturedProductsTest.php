<?php

namespace Tests\Feature\Catalog;

use App\Http\Controllers\Backend\FeaturedProductsController;
use App\Models\Product\FeaturedProduct;
use App\Models\Product\Product;
use App\Services\CatalogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Ana səhifənin vitrini: "Populyar" sıralaması və 12 ətir limiti */
class FeaturedProductsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('products', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->string('name'), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('featured_products', fn (Blueprint $t) => [$t->id(), $t->integer('product_id')->unique(), $t->unsignedSmallInteger('position'), $t->timestamps()]);
        foreach (range(1, 20) as $id) {
            DB::table('products')->insert(['id' => $id, 'name' => "P$id"]);
        }
    }

    private function sorted(?string $sort, bool $popular = true): array
    {
        return app(CatalogService::class)->applySort(Product::query(), $sort, $popular)->pluck('id')->all();
    }

    public function test_popular_puts_featured_first_in_admin_order_then_newest(): void
    {
        foreach ([5 => 1, 2 => 2, 9 => 3] as $id => $position) {
            FeaturedProduct::create(['product_id' => $id, 'position' => $position]);
        }
        $ids = $this->sorted('popular');
        $this->assertSame([5, 2, 9, 20, 19], array_slice($ids, 0, 5), '3 seçilib — qalan yerləri ən yenilər tutur');
        $this->assertCount(20, $ids, 'təkrar yoxdur');

        $this->assertSame([20, 19, 18], array_slice($this->sorted('newest'), 0, 3), 'başqa sıralamada vitrin nəzərə alınmır');
        $this->assertSame([20, 19, 18], array_slice($this->sorted('popular', false), 0, 3), 'kateqoriya/brend səhifəsində Populyar yoxdur');
    }

    public function test_store_respects_limit_and_destroy_renumbers(): void
    {
        $controller = app(FeaturedProductsController::class);
        foreach (range(1, FeaturedProduct::LIMIT + 1) as $id) {
            $controller->store(Request::create('/', 'POST', ['product_id' => $id]));
        }
        $this->assertSame(FeaturedProduct::LIMIT, FeaturedProduct::count(), '13-cü əlavə olunmur');
        $this->assertFalse(FeaturedProduct::where('product_id', FeaturedProduct::LIMIT + 1)->exists());

        $controller->destroy(FeaturedProduct::where('product_id', 1)->first());
        $this->assertSame(range(1, FeaturedProduct::LIMIT - 1), FeaturedProduct::orderBy('position')->pluck('position')->all());
        $this->assertSame(2, FeaturedProduct::orderBy('position')->value('product_id'));
    }
}
