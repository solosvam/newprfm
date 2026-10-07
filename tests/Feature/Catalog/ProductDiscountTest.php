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
use Illuminate\Support\Carbon;
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

    public function test_ended_discount_does_not_block_new_one_in_the_same_minute(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-10-07 03:55:42');
        $controller = app(ProductDiscountsController::class);
        $active = $this->discount(['starts_at' => '2026-10-06 03:54:00', 'ends_at' => '2026-10-08 00:00:00']);

        $controller->end($active);
        $this->assertSame('2026-10-07 03:55:00', $active->fresh()->ends_at->format('Y-m-d H:i:s'));

        // admin indiki dəqiqəni (03:55) başlama kimi seçir — bitmiş endirim mane olmur
        $controller->store(Request::create('/', 'POST', ['percent' => 20, 'starts_at' => '2026-10-07T03:55', 'ends_at' => '2026-10-09T03:55']), Product::find(1));
        $this->assertSame(2, ProductDiscount::count());
        $this->assertSame('20', ProductDiscount::latest('id')->first()->percentLabel());
        Carbon::setTestNow();
    }

    public function test_admin_edits_active_and_scheduled_discounts_and_deletes_any(): void
    {
        Queue::fake();
        $controller = app(ProductDiscountsController::class);
        $active = $this->discount();
        $originalStart = $active->starts_at->format('Y-m-d H:i:s');

        // aktiv: faiz və bitmə dəyişir, başlama göndərilsə də dəyişmir
        $controller->update(Request::create('/', 'PUT', ['percent' => 25, 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(4)->format('Y-m-d\TH:i')]), $active);
        $active->refresh();
        $this->assertSame(['25', $originalStart], [$active->percentLabel(), $active->starts_at->format('Y-m-d H:i:s')]);
        Queue::assertPushed(NotifyPriceDrop::class);   // faiz artdı

        // planlaşdırılmış: özü ilə üst-üstə düşmə sayılmır, başqası ilə — xəta
        $scheduled = $this->discount(['starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12)]);
        $controller->update(Request::create('/', 'PUT', ['percent' => 30, 'starts_at' => now()->addDays(11)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(13)->format('Y-m-d\TH:i')]), $scheduled);
        $this->assertSame('30', $scheduled->fresh()->percentLabel());
        try {
            $controller->update(Request::create('/', 'PUT', ['percent' => 30, 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(13)->format('Y-m-d\TH:i')]), $scheduled->fresh());
            $this->fail('Aktiv endirimlə üst-üstə düşən dövr qəbul olunmamalıdır');
        } catch (ValidationException $e) {
            $this->assertSame('discountEdit', $e->errorBag);
        }

        // bitmiş endirim redaktə olunmur, amma silinir; aktiv də silinir
        $ended = $this->discount(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(2)]);
        $controller->update(Request::create('/', 'PUT', ['percent' => 50, 'ends_at' => now()->addDay()->format('Y-m-d\TH:i')]), $ended);
        $this->assertSame('15', $ended->fresh()->percentLabel());
        $controller->destroy($ended);
        $controller->destroy($active);
        $this->assertSame([$scheduled->id], ProductDiscount::pluck('id')->all());
    }
}
