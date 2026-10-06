<?php

namespace Tests\Feature\Ferrum;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FerrumOrderTest extends TestCase
{
    private string $card;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->string('description')->nullable(), $t->timestamps()]);
        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name')->nullable(), $t->string('name_az')->nullable()]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('mobile'), $t->string('email')->nullable(), $t->string('gender')->nullable(), $t->timestamps()]);
        Schema::create('customer_credit_profiles', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('father_name')->nullable(), $t->string('fin')->nullable(),
            $t->string('relative_1_name')->nullable(), $t->string('relative_1_phone')->nullable(), $t->string('relative_2_name')->nullable(), $t->string('relative_2_phone')->nullable(),
            $t->string('id_card_front')->nullable(), $t->string('id_card_back')->nullable(), $t->string('workplace_name')->nullable(), $t->decimal('salary')->nullable(), $t->string('position')->nullable(), $t->string('id_card_series')->nullable(), $t->string('id_card_number')->nullable(), $t->timestamps()]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->string('city'), $t->string('district')->nullable(), $t->string('address'),
            $t->string('building')->nullable(), $t->string('entrance')->nullable(), $t->string('floor')->nullable(), $t->string('apartment')->nullable()]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->string('order_no'), $t->integer('customer_id')->nullable(), $t->integer('customer_address_id')->nullable(),
            $t->integer('payment_method_id'), $t->integer('order_status_id'), $t->decimal('subtotal'), $t->decimal('discount')->default(0), $t->decimal('delivery_fee')->default(0), $t->decimal('total'), $t->timestamps()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->integer('brand_id')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->integer('product_id'), $t->integer('product_variant_id'),
            $t->decimal('unit_price'), $t->integer('quantity'), $t->integer('cancelled_quantity')->default(0), $t->decimal('total'), $t->timestamps()]);
        Schema::create('credit_periods', fn (Blueprint $t) => [$t->id(), $t->integer('month'), $t->decimal('interest_rate')]);
        Schema::create('credit_applications', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->integer('order_id'), $t->integer('credit_period_id'),
            $t->decimal('interest_rate'), $t->decimal('total'), $t->decimal('monthly'), $t->integer('credit_status_id')->nullable(), $t->timestamps()]);
        (require database_path('migrations/2026_09_30_120000_add_ferrum_permission_and_access_log.php'))->up();
        $this->assertTrue(DB::table('permissions')->where('name', 'ferrum')->where('guard_name', 'admin')->exists());
        DB::table('permissions')->delete(); // Spatie roles cədvəli yoxdur — icazəni Gate::before yoxlayır

        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'new', 'name_az' => 'Yeni']);
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'installment', 'name_az' => 'Hissə-hissə']);
        DB::table('customers')->insert(['id' => 5, 'name' => 'Aysel', 'surname' => 'Məmmədova', 'mobile' => '994501234567']);
        $this->card = 'test-'.uniqid().'.webp';
        file_put_contents(\App\Services\IdCard\IdCardStorage::ensureDirectory().'/'.$this->card, 'IMG');
        DB::table('customer_credit_profiles')->insert(['customer_id' => 5, 'father_name' => 'Elçin', 'fin' => '5abc12d', 'relative_1_name' => 'Anar', 'relative_1_phone' => '0551112233',
            'id_card_front' => $this->card, 'workplace_name' => 'ABC MMC', 'salary' => 1200, 'id_card_series' => 'AZE', 'id_card_number' => '12345678']);
        DB::table('customer_addresses')->insert(['id' => 3, 'customer_id' => 5, 'city' => 'Bakı', 'address' => 'Nizami küç. 10', 'apartment' => '12']);
        DB::table('orders')->insert(['id' => 77, 'order_no' => 'PS260930000077', 'customer_id' => 5, 'customer_address_id' => 3, 'payment_method_id' => 1, 'order_status_id' => 1,
            'subtotal' => 300, 'total' => 330, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Chanel']);
        DB::table('products')->insert(['id' => 1, 'name' => 'Bleu', 'brand_id' => 1]);
        DB::table('sizes')->insert(['id' => 1, 'name_az' => '100 ml']);
        DB::table('product_variants')->insert(['id' => 1, 'product_id' => 1, 'size_id' => 1]);
        DB::table('order_items')->insert(['order_id' => 77, 'product_id' => 1, 'product_variant_id' => 1, 'unit_price' => 150, 'quantity' => 2, 'cancelled_quantity' => 0, 'total' => 300]);
        DB::table('credit_periods')->insert(['id' => 1, 'month' => 6, 'interest_rate' => 10]);
        DB::table('credit_applications')->insert(['customer_id' => 5, 'order_id' => 77, 'credit_period_id' => 1, 'interest_rate' => 10, 'total' => 330, 'monthly' => 55]);
    }

    protected function tearDown(): void
    {
        @unlink(\App\Services\IdCard\IdCardStorage::directory().'/'.$this->card);
        parent::tearDown();
    }

    private function admin(bool $allowed = true): void
    {
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        Gate::before(fn () => $allowed);
        $this->actingAs($user, 'admin');
    }

    public function test_order_data_for_extension(): void
    {
        $this->admin();
        $json = $this->getJson('/admin/ferrum/orders/ps260930000077')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->assertSame('PS260930000077', $json['order']['order_no']);
        $this->assertSame('5ABC12D', $json['customer']['fin']);
        $this->assertSame(['months' => 6, 'interest_rate' => 10, 'total' => 330, 'monthly' => 55], $json['credit']);
        $this->assertSame('Bakı, Nizami küç. 10, mənzil 12', $json['address']['full']);
        $this->assertSame([['brand' => 'Chanel', 'product' => 'Bleu', 'size' => '100 ml', 'quantity' => 2, 'unit_price' => 150, 'total' => 300]], $json['items']);
        $this->assertSame(['Qohum 2', 'Vəsiqə (arxa)'], $json['missing']);
        $this->assertSame('AZE', $json['customer']['id_card_series']);
        $this->assertSame('12345678', $json['customer']['id_card_number']);
        $this->assertArrayNotHasKey('position', $json['work']);
        $this->assertSame([], $json['warnings']);
        $this->assertSame('/admin/ferrum/orders/77/id-card/front', $json['id_card']['front']); // nisbi link
        $this->assertNull($json['id_card']['back']);
        $this->getJson('/admin/ferrum/orders/77')->assertOk()->assertJsonPath('order.id', 77); // id ilə də
        $this->assertSame(2, DB::table('sensitive_access_logs')->where('action', 'ferrum_order')->where('user_id', 7)->count());

        // yeni vəsiqə (AA) — arxa üz tələb olunmur
        DB::table('customer_credit_profiles')->where('customer_id', 5)->update(['id_card_series' => 'AA']);
        $this->assertSame(['Qohum 2'], $this->getJson('/admin/ferrum/orders/77')->json('missing'));

        $this->get($json['id_card']['front'])->assertOk();
        $this->get('/admin/ferrum/orders/77/id-card/back')->assertNotFound();
        $this->assertSame(1, DB::table('sensitive_access_logs')->where('action', 'ferrum_id_card_front')->count());
    }

    public function test_not_found_forbidden_and_guest(): void
    {
        $this->getJson('/admin/ferrum/orders/77')->assertUnauthorized();
        $this->admin(false);
        $this->getJson('/admin/ferrum/orders/77')->assertForbidden();
        $this->assertSame(0, DB::table('sensitive_access_logs')->count());
    }

    public function test_unknown_order(): void
    {
        $this->admin();
        $this->getJson('/admin/ferrum/orders/PS000')->assertNotFound()->assertJsonPath('message', 'Sifariş tapılmadı: PS000');
    }

    public function test_non_installment_order_returns_no_data(): void
    {
        $this->admin();
        DB::table('payment_methods')->insert(['id' => 2, 'code' => 'card_online', 'name_az' => 'Saytda onlayn ödəniş']);
        DB::table('orders')->where('id', 77)->update(['payment_method_id' => 2]);
        $response = $this->getJson('/admin/ferrum/orders/77')->assertStatus(422)
            ->assertJsonPath('message', 'PS260930000077 taksit sifarişi deyil (Saytda onlayn ödəniş). Ferrum yalnız taksit sifarişləri üçündür.');
        $this->assertArrayNotHasKey('customer', $response->json());
        $this->get('/admin/ferrum/orders/77/id-card/front')->assertStatus(422);
        $this->assertSame(0, DB::table('sensitive_access_logs')->count());
    }
}
