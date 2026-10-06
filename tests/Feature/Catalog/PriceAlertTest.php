<?php

namespace Tests\Feature\Catalog;

use App\Jobs\NotifyPriceDrop;
use App\Models\Customer\Customer;
use App\Models\Product\PriceAlert;
use App\Models\Product\ProductVariant;
use App\Services\PushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** "Qiymət enəndə xəbər ver": ölçü üzrə abunəlik, qiymət enəndə push (bir dəfə) */
class PriceAlertTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.onesignal.app_id' => 'app', 'services.onesignal.rest_api_key' => 'key']);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->boolean('active')->default(true), $t->rememberToken(), $t->timestamps()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->integer('brand_id'), $t->string('name'), $t->string('slug'), $t->boolean('active')->default(true), $t->timestamps()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')->default(true)]);
        Schema::create('price_alerts', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->integer('product_variant_id'), $t->decimal('price', 10, 2), $t->timestamp('notified_at')->nullable(), $t->timestamps()]);
        (require database_path('migrations/2026_10_07_170000_create_product_discounts_table.php'))->up(); // məhsul endirimi (activeDiscount)
        DB::table('brands')->insert(['id' => 1, 'name' => 'Christian Dior']);
        DB::table('sizes')->insert(['id' => 1, 'name_az' => '100 ml']);
        DB::table('products')->insert(['id' => 1, 'brand_id' => 1, 'name' => 'Sauvage', 'slug' => 'sauvage']);
        DB::table('product_variants')->insert(['id' => 1, 'product_id' => 1, 'size_id' => 1, 'price' => 200]);
        $this->customer = Customer::forceCreate(['name' => 'Aysel']);
    }

    public function test_guest_cannot_subscribe_and_customer_toggles(): void
    {
        $this->postJson('/price-alerts/toggle', ['variant_id' => 1])->assertUnauthorized();

        $this->actingAs($this->customer)->postJson('/price-alerts/toggle', ['variant_id' => 1])->assertOk()->assertJsonPath('subscribed', true);
        $this->assertEquals(200, PriceAlert::first()->price);
        $this->actingAs($this->customer)->postJson('/price-alerts/toggle', ['variant_id' => 1])->assertOk()->assertJsonPath('subscribed', false);
        $this->assertSame(0, PriceAlert::count());
    }

    public function test_only_price_drop_queues_notification(): void
    {
        Queue::fake();
        ProductVariant::find(1)->update(['price' => 250]);
        ProductVariant::find(1)->update(['active' => 0]);
        Queue::assertNothingPushed();

        ProductVariant::find(1)->update(['price' => 180, 'active' => 1]);
        Queue::assertPushed(NotifyPriceDrop::class, fn ($job) => $job->variantId === 1);
    }

    public function test_push_goes_once_to_subscribers_above_new_price(): void
    {
        Http::fake(['api.onesignal.com/*' => Http::response(['id' => 'n1'])]);
        $other = Customer::forceCreate(['name' => 'B']);
        PriceAlert::create(['customer_id' => $this->customer->id, 'product_variant_id' => 1, 'price' => 200]);
        PriceAlert::create(['customer_id' => $other->id, 'product_variant_id' => 1, 'price' => 170]); // daha ucuz qiymətlə abunə olub

        DB::table('product_variants')->where('id', 1)->update(['price' => 180]);
        (new NotifyPriceDrop(1))->handle(app(PushService::class));
        (new NotifyPriceDrop(1))->handle(app(PushService::class)); // təkrar — göndərilmir

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['include_aliases']['external_id'] === ['customer-'.$this->customer->id]
            && str_contains($request['contents']['az'], 'Christian Dior Sauvage 100 ml'));
        $this->assertNotNull(PriceAlert::where('customer_id', $this->customer->id)->value('notified_at'));
        $this->assertNull(PriceAlert::where('customer_id', $other->id)->value('notified_at'));
    }

    public function test_without_push_key_alert_stays_open(): void
    {
        config(['services.onesignal.rest_api_key' => null]);
        Http::fake();
        PriceAlert::create(['customer_id' => $this->customer->id, 'product_variant_id' => 1, 'price' => 200]);
        DB::table('product_variants')->where('id', 1)->update(['price' => 150]);
        (new NotifyPriceDrop(1))->handle(app(PushService::class));

        Http::assertNothingSent();
        $this->assertNull(PriceAlert::first()->notified_at);
    }
}
