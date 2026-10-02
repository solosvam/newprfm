<?php

namespace Tests\Feature\Admin;

use App\Models\Order\Order;
use App\Models\User;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use OrderStatusFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('email')->nullable(), $t->string('password')->nullable(), $t->boolean('active')->default(true), $t->timestamps()]);
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')->nullable()]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')->nullable()]);
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'cash', 'name_az' => 'Qapıda nağd'], ['id' => 2, 'code' => 'card_online', 'name_az' => 'Kartla onlayn'], ['id' => 3, 'code' => 'birbank_installment', 'name_az' => 'Birbank taksit'], ['id' => 4, 'code' => 'installment', 'name_az' => 'Hissə-hissə ödəniş']]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id')->nullable(), $t->unsignedBigInteger('order_status_id')->nullable(), $t->unsignedBigInteger('payment_method_id')->default(1), $t->decimal('total', 12, 2)->default(0), $t->decimal('delivery_fee', 12, 2)->default(0), $t->decimal('gift_wrap_fee', 12, 2)->default(0), $t->boolean('one_click')->default(false), $t->string('source', 20)->default('website'), $t->timestamps()]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->unsignedBigInteger('product_id')->nullable(), $t->integer('quantity')->default(1), $t->integer('cancelled_quantity')->default(0), $t->decimal('total', 12, 2)->default(0)]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('brand_id')->nullable()]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        Schema::create('warehouse_requests', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->timestamps()]);
        Schema::create('warehouse_request_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('warehouse_request_id')]);
        Schema::create('warehouse_offers', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('warehouse_request_item_id')]);
        Schema::create('credit_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('credit_applications', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('credit_status_id')]);
        Schema::create('product_reviews', fn (Blueprint $t) => [$t->id(), $t->boolean('active')->default(false)]);
        Schema::create('product_search_logs', fn (Blueprint $t) => [$t->id(), $t->string('query'), $t->string('normalized_query'), $t->unsignedSmallInteger('result_count')->default(0), $t->timestamp('searched_at')]);
        Schema::create('payments', fn (Blueprint $t) => [$t->id(), $t->decimal('amount', 12, 2), $t->string('status'), $t->timestamps()]);
        Schema::create('order_item_cancellations', fn (Blueprint $t) => [$t->id(), $t->string('refund_status')->nullable()]);
        Schema::create('order_item_allocations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_item_id'), $t->unsignedBigInteger('warehouse_id')->nullable(), $t->integer('quantity'), $t->decimal('unit_cost', 12, 2), $t->string('status')]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->integer('old_customer_id')->nullable(), $t->string('source', 20)->nullable(), $t->boolean('active')->default(true), $t->decimal('bonus_balance', 12, 2)->default(0), $t->timestamps()]);
        Schema::create('warehouses', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        (require database_path('migrations/2026_09_29_200000_create_finance_tables.php'))->up();
        $this->orderStatusFixtures();
        DB::table('order_statuses')->where('code', 'new')->update(['name_az' => 'Sifariş verildi']);
        DB::table('order_statuses')->where('code', 'sent')->update(['name_az' => 'Yola çıxdı']);

        Role::create(['name' => 'Admin', 'guard_name' => 'admin']);
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        $this->admin->assignRole('Admin');
        Carbon::setTestNow('2026-10-02 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(string $at, float $total, string $status = 'new', int $method = 1, float $cost = 0, float $fee = 0): Order
    {
        $order = Order::forceCreate(['order_status_id' => DB::table('order_statuses')->where('code', $status)->value('id'), 'total' => $total, 'payment_method_id' => $method, 'delivery_fee' => $fee]);
        $order->forceFill(['created_at' => $at])->save();
        if ($cost) {
            $item = DB::table('order_items')->insertGetId(['order_id' => $order->id]);
            DB::table('order_item_allocations')->insert(['order_item_id' => $item, 'quantity' => 1, 'unit_cost' => $cost, 'status' => 'picked']);
        }

        return $order;
    }

    public function test_stats_compare_with_previous_period_and_skip_cancelled(): void
    {
        $this->order('2026-10-02 10:00', 100);
        $this->order('2026-10-02 11:00', 300, 'sent');
        $this->order('2026-10-02 12:00', 999, 'cancelled');
        $this->order('2026-10-01 09:00', 200, 'delivered');   // dünən bu saata qədər
        $this->order('2026-10-01 18:00', 500, 'delivered');   // dünən bu saatdan sonra — müqayisəyə düşmür
        DB::table('customers')->insert([
            ['name' => 'Yeni', 'old_customer_id' => null, 'created_at' => '2026-10-02 09:00'],
            ['name' => 'Köçürülən', 'old_customer_id' => 7, 'created_at' => '2026-10-02 09:00'],
        ]);

        $stats = app(AdminDashboard::class)->stats('today');

        $this->assertSame(2, $stats['orders']['value']);
        $this->assertSame(1, $stats['orders']['previous']);
        $this->assertSame(100.0, $stats['orders']['change']);
        $this->assertSame(400.0, $stats['revenue']['value']);
        $this->assertSame(200.0, $stats['revenue']['previous']);
        $this->assertSame(200.0, $stats['average']['value']);
        $this->assertSame(1, $stats['customers']['value']);
        $this->assertNull($stats['customers']['change']);
    }

    public function test_profit_counts_only_delivered_orders_and_payment_split(): void
    {
        $this->order('2026-10-02 10:00', 205, 'delivered', 1, 120, 5);   // mal 200 − alış 120 = 80
        $this->order('2026-10-02 11:00', 300, 'delivered', 3, 250);       // 300 − 250 = 50
        $this->order('2026-10-02 12:00', 400, 'sent', 3, 300);            // hələ təhvil verilməyib
        $this->order('2026-10-02 13:00', 999, 'cancelled', 2);
        DB::table('order_item_allocations')->insert(['order_item_id' => 1, 'quantity' => 1, 'unit_cost' => 500, 'status' => 'cancelled']);

        $service = app(AdminDashboard::class);
        $profit = $service->stats('today')['profit'];
        $this->assertSame(130.0, $profit['value']);
        $this->assertSame(2, $profit['orders']);
        $this->assertSame(26.0, $profit['margin']);

        $split = collect($service->paymentMethods('today'))->keyBy('code');
        $this->assertSame(['birbank_installment', 'cash'], $split->keys()->all()); // ləğv olunan kart xaric, məbləğə görə
        $this->assertSame(700.0, $split['birbank_installment']['sum']);
        $this->assertSame(2, $split['birbank_installment']['orders']);
    }

    public function test_week_period_compares_with_same_moment_last_week(): void
    {
        $this->order('2026-09-28 10:00', 100);   // bu həftənin bazar ertəsi
        $this->order('2026-09-23 10:00', 40);    // keçən çərşənbə — keçən həftə bu vaxta qədər
        $this->order('2026-09-26 10:00', 999);   // keçən şənbə — müqayisəyə düşmür
        $revenue = app(AdminDashboard::class)->stats('week')['revenue'];
        $this->assertSame(100.0, $revenue['value']);
        $this->assertSame(40.0, $revenue['previous']);
    }

    public function test_top_products_subtract_cancelled_quantity(): void
    {
        DB::table('brands')->insert(['id' => 1, 'name' => 'Dior']);
        DB::table('products')->insert([['id' => 1, 'name' => 'Sauvage', 'brand_id' => 1], ['id' => 2, 'name' => 'Fahrenheit', 'brand_id' => 1]]);
        DB::table('product_images')->insert([['product_id' => 1, 'image' => 'b.jpg', 'sort_order' => 2], ['product_id' => 1, 'image' => 'a.jpg', 'sort_order' => 1]]);
        $a = $this->order('2026-10-02 10:00', 300);
        DB::table('order_items')->insert([
            ['order_id' => $a->id, 'product_id' => 1, 'quantity' => 3, 'cancelled_quantity' => 1, 'total' => 200],
            ['order_id' => $a->id, 'product_id' => 2, 'quantity' => 1, 'cancelled_quantity' => 0, 'total' => 100],
        ]);
        $c = $this->order('2026-10-02 11:00', 999, 'cancelled');
        DB::table('order_items')->insert(['order_id' => $c->id, 'product_id' => 2, 'quantity' => 5, 'cancelled_quantity' => 0, 'total' => 999]);

        $top = app(AdminDashboard::class)->topProducts('today');
        $this->assertSame(['Sauvage', 'Fahrenheit'], array_column($top, 'name'));
        $this->assertSame(2, $top[0]['quantity']);
        $this->assertSame('a.jpg', $top[0]['image']);
        $this->assertSame('Dior', $top[0]['brand']);
    }

    public function test_attention_counts(): void
    {
        $o = $this->order('2026-10-02 10:00', 100, 'warehouse_requested');
        DB::table('warehouse_requests')->insert([['id' => 1, 'order_id' => $o->id, 'created_at' => '2026-10-02 11:00'], ['id' => 2, 'order_id' => $o->id, 'created_at' => '2026-10-02 14:30']]);
        DB::table('warehouse_request_items')->insert([['id' => 1, 'warehouse_request_id' => 1], ['id' => 2, 'warehouse_request_id' => 1], ['id' => 3, 'warehouse_request_id' => 2]]);
        DB::table('warehouse_offers')->insert(['warehouse_request_item_id' => 2]);
        $this->order('2026-10-02 10:00', 50)->forceFill(['one_click' => true])->save();
        $sent = $this->order('2026-09-30 10:00', 80, 'sent');
        $sent->forceFill(['updated_at' => '2026-10-01 09:00'])->saveQuietly();
        DB::table('credit_statuses')->insert([['id' => 1, 'code' => 'pending'], ['id' => 2, 'code' => 'approved']]);
        DB::table('credit_applications')->insert([['credit_status_id' => 1], ['credit_status_id' => 2]]);
        DB::table('product_reviews')->insert([['active' => false], ['active' => true]]);
        DB::table('order_item_cancellations')->insert([['refund_status' => 'pending'], ['refund_status' => 'refunded']]);

        $this->assertSame(
            ['warehouse' => 1, 'easy_orders' => 1, 'credit' => 1, 'reviews' => 1, 'refunds' => 1, 'courier' => 1],
            app(AdminDashboard::class)->attention()
        );
    }

    public function test_searches_online_payments_and_sources(): void
    {
        foreach ([['Dior Savaj', 'dior savaj', 0], ['dior savaj', 'dior savaj', 0], ['ysl', 'ysl', 0], ['sauvage', 'sauvage', 4]] as [$q, $n, $c]) {
            DB::table('product_search_logs')->insert(['query' => $q, 'normalized_query' => $n, 'result_count' => $c, 'searched_at' => '2026-10-02 12:00']);
        }
        DB::table('product_search_logs')->insert(['query' => 'köhnə', 'normalized_query' => 'kohne', 'result_count' => 0, 'searched_at' => '2026-09-20 12:00']);
        $search = app(AdminDashboard::class)->searches('today');
        $this->assertSame([4, 1, 3], [$search['total'], $search['found'], $search['empty']]);
        $this->assertSame(2, $search['top_empty'][0]['count']);
        $this->assertSame('dior savaj', mb_strtolower($search['top_empty'][0]['query']));

        DB::table('payments')->insert([
            ['amount' => 100, 'status' => 'paid', 'created_at' => '2026-10-02 10:00'],
            ['amount' => 50, 'status' => 'paid', 'created_at' => '2026-10-02 11:00'],
            ['amount' => 70, 'status' => 'failed', 'created_at' => '2026-10-02 11:30'],
            ['amount' => 70, 'status' => 'cancelled', 'created_at' => '2026-10-02 11:40'],
            ['amount' => 70, 'status' => 'pending', 'created_at' => '2026-10-02 12:00'],
        ]);
        $online = app(AdminDashboard::class)->onlinePayments('today');
        $this->assertSame(['paid' => 2, 'paid_sum' => 150.0, 'failed' => 2, 'failed_sum' => 140.0, 'pending' => 1, 'pending_sum' => 70.0, 'rate' => 50.0], $online);

        $this->order('2026-10-02 10:00', 100)->forceFill(['source' => 'customer'])->save();
        $this->order('2026-10-02 10:00', 100)->forceFill(['source' => 'customer'])->save();
        $this->order('2026-10-02 10:00', 60)->forceFill(['source' => 'website', 'one_click' => true])->save();
        $this->order('2026-10-02 10:00', 80)->forceFill(['source' => 'operator'])->save();
        $this->order('2026-10-02 10:00', 999, 'cancelled')->forceFill(['source' => 'operator'])->save();
        $sources = collect(app(AdminDashboard::class)->sources('today'))->keyBy('code');
        $this->assertSame(['customer', 'one_click', 'operator'], $sources->keys()->sort()->values()->all());
        $this->assertSame(2, $sources['customer']['orders']);
        $this->assertSame(80.0, $sources['operator']['sum']);
        $this->assertSame(50.0, $sources['customer']['share']);
    }

    public function test_new_customers_by_source_skip_legacy_and_unverified(): void
    {
        DB::table('customers')->insert([
            ['source' => 'crm', 'active' => true, 'old_customer_id' => null, 'created_at' => '2026-10-02 09:00'],
            ['source' => 'crm', 'active' => true, 'old_customer_id' => null, 'created_at' => '2026-10-02 10:00'],
            ['source' => 'website', 'active' => true, 'old_customer_id' => null, 'created_at' => '2026-10-02 10:00'],
            ['source' => 'website', 'active' => false, 'old_customer_id' => null, 'created_at' => '2026-10-02 11:00'], // SMS təsdiqlənməyib
            ['source' => 'legacy', 'active' => true, 'old_customer_id' => 9, 'created_at' => '2026-10-02 11:00'],
        ]);
        $service = app(AdminDashboard::class);
        $this->assertSame(3, $service->stats('today')['customers']['value']);
        $this->assertSame(['crm' => 2, 'website' => 1], collect($service->customerSources('today'))->pluck('count', 'code')->all());
    }

    public function test_finance_summary(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        $finance = app(\App\Services\FinanceService::class);
        $account = $finance->courierAccount($courier);
        DB::table('warehouses')->insert(['id' => 1, 'name_az' => 'A']);
        $whAccount = $finance->warehouseAccount(\App\Models\Procurement\Warehouse::find(1));
        $customerAcc = \App\Models\Finance\FinanceAccount::where('type', 'customer')->first() ?? \App\Models\Finance\FinanceAccount::create(['type' => 'customer', 'name' => 'Müştərilər']);
        DB::table('money_movements')->insert([
            ['from_account_id' => $customerAcc->id, 'to_account_id' => $account->id, 'amount' => 150, 'kind' => 'customer_cash', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('order_item_allocations')->insert(['order_item_id' => 1, 'warehouse_id' => 1, 'quantity' => 2, 'unit_cost' => 40, 'status' => 'picked']);
        DB::table('customers')->insert([['bonus_balance' => 12.5], ['bonus_balance' => 7.5]]);

        $data = app(AdminDashboard::class)->finance();
        $this->assertSame(150.0, $data['couriers_owe']);
        $this->assertSame('Fərid', $data['couriers'][0]['name']);
        $this->assertSame(80.0, $data['warehouses_debt']);
        $this->assertSame(1, $data['warehouses']);
        $this->assertSame(20.0, $data['bonus']);
    }

    public function test_sales_series_and_active_statuses(): void
    {
        $this->order('2026-10-02 10:00', 100);
        $this->order('2026-09-30 10:00', 50, 'sent');
        $this->order('2026-09-20 10:00', 70); // 7 gündən köhnə

        $service = app(AdminDashboard::class);
        $sales = $service->sales(7);
        $this->assertCount(7, $sales['revenue']);
        $this->assertSame('Bu gün', end($sales['labels']));
        $this->assertSame(100.0, end($sales['revenue']));
        $this->assertSame([0, 0, 0, 0, 1, 0, 1], $sales['orders']);

        $active = collect($service->activeStatuses())->pluck('count', 'code');
        $this->assertSame(2, $active['new']);
        $this->assertSame(1, $active['sent']);
    }

    public function test_main_page_renders_for_admin(): void
    {
        $this->order('2026-10-02 10:00', 100);
        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.main', ['period' => 'week']))
            ->assertOk()->getContent();

        foreach (['Yeni müştərilər', 'Saytda axtarış', 'Onlayn ödənişlər', 'Sifariş mənbələri', 'Diqqət tələb edənlər', 'Ən çox satılanlar', 'Aktiv sifarişlər', 'Statistika', 'Bu həftə', 'Dövriyyə', 'Mənfəət', 'Qapıda nağd', 'dashRevenueChart', 'dashPaymentsChart'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        if ($dump = env('DASHBOARD_HTML_DUMP')) {
            file_put_contents($dump, $html);
        }
    }
}
