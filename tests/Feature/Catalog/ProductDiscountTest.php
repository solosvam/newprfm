<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\PromoCodeException;
use App\Http\Controllers\Backend\Product\ProductDiscountsController;
use App\Jobs\NotifyPriceDrop;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductDiscount;
use App\Models\Product\ProductVariant;
use App\Services\CatalogService;
use App\Services\PromoCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Məhsul endirimi: endirimli qiymət qaydaları, promo kod istisnası, qiymətə görə sıralama, admin yaratma */
class ProductDiscountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->boolean('active')->default(true), $t->rememberToken(), $t->timestamps()]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->decimal('price', 10, 2), $t->boolean('active')->default(true)]);
        (require database_path('migrations/2026_10_07_170000_create_product_discounts_table.php'))->up();
        DB::table('products')->insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        DB::table('product_variants')->insert([['id' => 1, 'product_id' => 1, 'price' => 98], ['id' => 2, 'product_id' => 2, 'price' => 90]]);
    }

    private function discount(array $attributes = []): ProductDiscount
    {
        return ProductDiscount::create($attributes + ['product_id' => 1, 'percent' => 15,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
    }

    private function variant(int $id = 1): ProductVariant
    {
        return ProductVariant::with('product.activeDiscount')->find($id);
    }

    public function test_sale_price_rules(): void
    {
        $this->assertSame(98.0, $this->variant()->salePrice(), 'endirim yoxdur');

        $this->discount();
        $this->assertSame(83.3, $this->variant()->salePrice(), '98 × 85% — qəpiklə');


        ProductDiscount::query()->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addDay()]);
        $this->assertSame(98.0, $this->variant()->salePrice(), 'hələ başlamayıb');
        ProductDiscount::query()->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        $this->assertSame(98.0, $this->variant()->salePrice(), 'bitib');
    }

    public function test_promo_applies_only_to_non_discounted_items(): void
    {
        $this->discount();
        $promo = app(PromoCodeService::class);
        $this->assertSame(180.0, $promo->subtotalFor([['variant_id' => 1, 'quantity' => 1], ['variant_id' => 2, 'quantity' => 2]]));

        $this->expectException(PromoCodeException::class);
        $promo->subtotalFor([['variant_id' => 1, 'quantity' => 1]]);
    }

    public function test_price_sort_uses_sale_price(): void
    {
        $sorted = fn () => app(CatalogService::class)->applySort(Product::query(), 'price_asc')->pluck('id')->all();
        $this->assertSame([2, 1], $sorted(), '90 < 98');
        $this->discount(); // 98 → 83.30
        $this->assertSame([1, 2], $sorted());
        $this->actingAs(Customer::forceCreate(['name' => 'X']));
        $this->assertSame([1, 2], $sorted(), 'qonaq da, müştəri də eyni qiyməti görür');
    }

    public function test_admin_creates_discount_without_overlap_and_notifies_subscribers_at_start(): void
    {
        Queue::fake();
        $controller = app(ProductDiscountsController::class);
        $start = now()->addDays(2)->startOfMinute();
        $controller->store(Request::create('/', 'POST', ['percent' => 20, 'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addDays(5)->format('Y-m-d\TH:i')]), Product::find(1));
        $this->assertSame('scheduled', ProductDiscount::first()->status());
        Queue::assertPushed(NotifyPriceDrop::class, fn ($job) => $job->variantId === 1 && $job->delay !== null);

        try {
            $controller->store(Request::create('/', 'POST', ['percent' => 10, 'starts_at' => $start->copy()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addDays(10)->format('Y-m-d\TH:i')]), Product::find(1));
            $this->fail('Üst-üstə düşən endirim qəbul olunmamalıdır');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('starts_at', $e->errors());
        }
        $this->assertSame(1, ProductDiscount::count());
    }
}
