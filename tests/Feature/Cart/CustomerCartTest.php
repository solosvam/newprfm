<?php

namespace Tests\Feature\Cart;

use App\Models\Customer\Customer;
use App\Services\CartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', function (Blueprint $t) { $t->integer('id')->primary(); $t->string('name'); $t->string('email')->nullable(); $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps(); });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->boolean('active')->default(true); });
        Schema::create('product_variants', function (Blueprint $t) { $t->id(); $t->integer('product_id'); $t->decimal('price', 12, 2)->default(10); $t->boolean('active')->default(true); });
        (require database_path('migrations/2026_10_02_110000_create_customer_cart_tables.php'))->up();
        DB::table('products')->insert([['id' => 1, 'active' => 1], ['id' => 2, 'active' => 0]]);
        DB::table('product_variants')->insert([
            ['id' => 1, 'product_id' => 1, 'active' => 1], ['id' => 2, 'product_id' => 1, 'active' => 1],
            ['id' => 3, 'product_id' => 1, 'active' => 0], ['id' => 4, 'product_id' => 2, 'active' => 1],
        ]);
        Customer::forceCreate(['id' => 1, 'name' => 'Bir']);
        Customer::forceCreate(['id' => 2, 'name' => 'İki']);
    }

    private function customer(int $id = 1): Customer
    {
        return Customer::findOrFail($id);
    }

    public function test_guest_merge_preserves_existing_items_and_adds_matching_quantities_once(): void
    {
        $service = app(CartService::class);
        $customer = $this->customer();
        $service->change($customer, 1, 'add', 2);
        $service->change($customer, 2, 'add', 1);
        $token = (string) Str::uuid();
        $guest = [['variant_id' => 1, 'quantity' => 3]];
        $items = $service->merge($customer, $guest, $token);
        $this->assertSame([5, 1], array_column($items, 'quantity'));
        $this->assertSame($items, $service->merge($customer, $guest, $token));
        $this->assertSame(1, DB::table('customer_cart_imports')->count());
    }

    public function test_different_import_batches_are_added_and_unavailable_variants_are_skipped(): void
    {
        $service = app(CartService::class);
        $customer = $this->customer();
        $guest = [['variant_id' => 1, 'quantity' => 2], ['variant_id' => 3, 'quantity' => 1],
            ['variant_id' => 4, 'quantity' => 1], ['variant_id' => 999, 'quantity' => 1]];
        $service->merge($customer, $guest, (string) Str::uuid());
        $items = $service->merge($customer, $guest, (string) Str::uuid());
        $this->assertCount(1, $items);
        $this->assertSame(4, $items[0]['quantity']);
    }

    public function test_mutations_are_scoped_to_customer_and_checkout_clear_does_not_delete_other_cart(): void
    {
        $service = app(CartService::class);
        $service->change($this->customer(), 1, 'add', 2);
        $service->change($this->customer(2), 1, 'add', 7);
        $this->assertSame(1, $service->change($this->customer(), 1, 'decrease')[0]['quantity']);
        $service->change($this->customer(), 2, 'add');
        $service->change($this->customer(), 2, 'remove');
        $service->clear($this->customer());
        $this->assertSame([], $service->items($this->customer()));
        $this->assertSame(7, $service->items($this->customer(2))[0]['quantity']);
    }

    public function test_failed_order_transaction_restores_cart_and_success_clears_all_items(): void
    {
        $service = app(CartService::class);
        $customer = $this->customer();
        $service->change($customer, 1, 'add');
        $service->change($customer, 2, 'add');
        try {
            DB::transaction(function () use ($service, $customer) {
                $service->clear($customer);
                throw new \RuntimeException('Order failed');
            });
        } catch (\RuntimeException) {}
        $this->assertCount(2, $service->items($customer));
        DB::transaction(fn () => $service->clear($customer));
        $this->assertSame([], $service->items($customer));
    }

    public function test_cart_routes_require_login_validate_input_and_ignore_supplied_customer_id(): void
    {
        $this->getJson('/cart/items')->assertUnauthorized();
        $this->postJson('/cart/merge', ['items' => []])->assertUnauthorized();
        $this->actingAs($this->customer())->postJson('/cart/items', [
            'customer_id' => 2, 'variant_id' => 1, 'action' => 'add', 'quantity' => 3,
        ])->assertOk()->assertJsonPath('items.0.quantity', 3);
        $this->assertSame([], app(CartService::class)->items($this->customer(2)));
        $this->postJson('/cart/items', ['variant_id' => 1, 'action' => 'add', 'quantity' => 0])->assertUnprocessable();
        $this->postJson('/cart/items', ['variant_id' => 3, 'action' => 'add'])->assertUnprocessable();
        $this->postJson('/cart/merge', ['token' => 'bad', 'items' => [['variant_id' => 2, 'quantity' => 1]]])->assertUnprocessable();
        $this->getJson('/cart/items')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_checkout_creates_order_and_empties_database_cart(): void
    {
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); $t->boolean('active'); });
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); $t->boolean('active'); });
        Schema::create('customer_addresses', function (Blueprint $t) { $t->id(); $t->integer('customer_id'); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->string('order_no'); $t->integer('customer_id'); $t->integer('customer_address_id');
            $t->integer('payment_method_id'); $t->integer('birbank_installment_months')->nullable();
            $t->string('payment_status'); $t->string('source'); $t->integer('order_status_id');
            $t->boolean('gift_wrap'); $t->text('customer_note')->nullable();
            foreach (['subtotal', 'discount', 'referral_discount', 'delivery_fee', 'gift_wrap_fee', 'total', 'bonus_earned'] as $column) $t->decimal($column, 12, 2)->default(0);
            $t->integer('promo_code_id')->nullable(); $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('product_id'); $t->integer('product_variant_id');
            $t->integer('quantity'); $t->decimal('unit_price', 12, 2); $t->decimal('total', 12, 2); $t->timestamps();
        });
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('status_id'); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->integer('order_id'); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note'); $t->timestamp('expires_at')->nullable(); $t->timestamp('expired_at')->nullable(); $t->timestamps();
        });
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'cash', 'active' => 1]);
        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'new', 'active' => 1]);
        DB::table('customer_addresses')->insert(['id' => 1, 'customer_id' => 1]);
        $service = app(CartService::class);
        $service->change($this->customer(), 1, 'add', 2);
        $service->change($this->customer(), 2, 'add');
        $body = ['cart' => $service->items($this->customer()), 'address_mode' => 'existing', 'address_id' => 1, 'payment_method_id' => 1];

        $this->actingAs($this->customer())->postJson('/checkout', $body + ['gift_wrap' => 'invalid'])->assertUnprocessable();
        $this->assertCount(2, $service->items($this->customer()));
        $this->postJson('/checkout', $body)->assertOk()->assertJsonPath('clear_cart', true)->assertJsonPath('cart', []);
        $this->assertSame([], $service->items($this->customer()));
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(2, DB::table('order_items')->count());
    }

    public function test_quantity_obeys_checkout_limit_and_remove_is_repeatable(): void
    {
        $service = app(CartService::class);
        $customer = $this->customer();
        $service->change($customer, 1, 'add', 98);
        $this->assertSame(99, $service->change($customer, 1, 'add', 3)[0]['quantity']);
        $service->change($customer, 1, 'remove');
        $this->assertSame([], $service->change($customer, 1, 'remove'));
    }
}
