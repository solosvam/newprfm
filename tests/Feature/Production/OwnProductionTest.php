<?php

namespace Tests\Feature\Production;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Production\OwnProduct;
use App\Models\Production\RawMaterial;
use App\Services\FinanceService;
use App\Services\OrderItemCancellationService;
use App\Services\Finance\SoldItemsReport;
use App\Services\Production\OwnProduction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class OwnProductionTest extends TestCase
{
    use OrderStatusFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name_az')->nullable(); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            $t->string('payment_status')->default('cod'); $t->unsignedBigInteger('courier_id')->nullable(); $t->unsignedBigInteger('payment_method_id')->nullable();
            foreach (['subtotal', 'discount', 'referral_discount', 'delivery_fee', 'gift_wrap_fee', 'courier_fee', 'total'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->integer('product_id')->nullable(); $t->integer('product_variant_id')->nullable();
            $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('list_price', 12, 2)->nullable();
            $t->integer('quantity'); $t->integer('cancelled_quantity')->default(0); $t->decimal('total', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('brands', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('name'); $t->unsignedBigInteger('brand_id')->nullable(); });
        Schema::create('sizes', function (Blueprint $t) { $t->id(); $t->string('name_az'); });
        Schema::create('product_variants', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('size_id')->nullable(); $t->decimal('price', 12, 2)->default(0); $t->boolean('active')->default(true); });
        foreach ([
            '2026_09_29_150000_create_procurement_tables.php',
            '2026_11_15_090000_add_is_own_to_warehouses.php',
            '2026_11_12_140000_add_opening_debt_to_warehouses.php',
            '2026_10_15_120000_add_direct_to_warehouse_requests.php',
            '2026_09_29_190000_add_supply_flow_to_order_item_allocations.php',
            '2026_11_02_100000_add_courier_pays_to_order_item_allocations.php',
            '2026_09_29_200000_create_finance_tables.php',
            '2026_10_16_100000_create_finance_categories.php',
            '2026_11_15_100000_create_production_tables.php',
            '2026_11_15_110000_production_permission_and_category.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->orderStatusFixtures();
        DB::table('brands')->insert(['id' => 1, 'name' => 'ParfumShop']);
        DB::table('products')->insert(['id' => 5, 'name' => 'Oud Nights', 'brand_id' => 1]);
        DB::table('sizes')->insert(['id' => 3, 'name_az' => '50 ml']);
        DB::table('product_variants')->insert(['id' => 9, 'product_id' => 5, 'size_id' => 3, 'price' => 60]);
    }

    private function supplier(): FinanceAccount
    {
        return FinanceAccount::create(['type' => 'supplier', 'name' => 'Şüşə MMC']);
    }

    /** Əsans 100 ml (200 AZN) + flakon 10 ədəd (30 AZN) alınıb */
    private function materials(): array
    {
        $essence = RawMaterial::create(['name' => 'Əsans', 'unit' => 'ml']);
        $bottle = RawMaterial::create(['name' => 'Flakon 50 ml', 'unit' => 'pcs']);
        app(OwnProduction::class)->purchase(['purchased_at' => today()->toDateString(), 'payment' => 'opening'], [
            ['raw_material_id' => $essence->id, 'quantity' => 100, 'unit_cost' => 2],
            ['raw_material_id' => $bottle->id, 'quantity' => 10, 'unit_cost' => 3],
        ], 7);

        return [$essence, $bottle];
    }

    private function order(int $quantity): Order
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'total' => 60 * $quantity]);
        $order->items()->create(['product_id' => 5, 'product_variant_id' => 9, 'quantity' => $quantity, 'unit_price' => 60]);

        return $order;
    }

    public function test_paid_purchase_is_an_expense_and_unpaid_purchase_is_a_debt(): void
    {
        $p = app(OwnProduction::class);
        $f = app(FinanceService::class);
        $supplier = $this->supplier();
        $cash = $f->system('cash');
        $alcohol = RawMaterial::create(['name' => 'Spirt', 'unit' => 'ml']);

        $p->purchase(['purchased_at' => today()->toDateString(), 'payment' => 'paid', 'supplier_account_id' => $supplier->id, 'account_id' => $cash->id],
            [['raw_material_id' => $alcohol->id, 'quantity' => 1000, 'unit_cost' => 0.05]], 7);
        $this->assertSame(-5000, $f->balances()[$cash->id]); // kassadan çıxdı
        $payment = MoneyMovement::where('kind', 'supplier_payment')->first();
        $this->assertSame('raw_material', $payment->category->code); // xərc kateqoriyası "Xammal"
        $this->assertContains('supplier_payment', MoneyMovement::OUT_KINDS);
        $this->assertSame(0, $p->supplierDebts()[$supplier->id]);

        // Borca: pul çıxmır, tədarükçüyə borc
        $p->purchase(['purchased_at' => today()->toDateString(), 'payment' => 'debt', 'supplier_account_id' => $supplier->id],
            [['raw_material_id' => $alcohol->id, 'quantity' => 1000, 'unit_cost' => 0.07]], 7);
        $this->assertSame(-5000, $f->balances()[$cash->id]);
        $this->assertSame(7000, $p->supplierDebts()[$supplier->id]);
        // Qalıq 2000 ml, orta maya (50 + 70) / 2000 = 0.06
        $this->assertSame(2000.0, $p->materialStocks()[$alcohol->id]);
        $this->assertEqualsWithDelta(0.06, (float) $alcohol->fresh()->avg_cost, 0.0001);

        try {
            $p->paySupplier($supplier, $cash, 80, null, 7);
            $this->fail('Borcdan çox ödənilməməli idi');
        } catch (ValidationException) {
        }
        $p->paySupplier($supplier, $cash, 70, null, 7);
        $this->assertSame(0, $p->supplierDebts()[$supplier->id]);
        $this->assertSame(-12000, $f->balances()[$cash->id]);
    }

    public function test_batch_consumes_materials_and_sets_unit_cost(): void
    {
        [$essence, $bottle] = $this->materials();
        $p = app(OwnProduction::class);
        $own = OwnProduct::create(['product_id' => 5, 'product_variant_id' => 9]);

        // 5 şüşə: 50 ml əsans (100 AZN) + 5 flakon (15 AZN) + 10 AZN əmək = 125 → 25 AZN / şüşə
        $batch = $p->produce($own, 5, today()->toDateString(), [
            ['raw_material_id' => $essence->id, 'quantity' => 50], ['raw_material_id' => $bottle->id, 'quantity' => 5],
        ], 10, null, 7);
        $this->assertSame('115.00', $batch->materials_cost);
        $this->assertEqualsWithDelta(25, (float) $batch->unit_cost, 0.0001);
        $this->assertSame(50.0, $p->materialStocks()[$essence->id]);
        $this->assertSame(5, $p->stockOf($own));

        // Xammal çatmır
        try {
            $p->produce($own, 10, today()->toDateString(), [['raw_material_id' => $bottle->id, 'quantity' => 10]], 0, null, 7);
            $this->fail('Flakon çatmamalı idi');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Flakon 50 ml çatmır', collect($e->errors())->flatten()->first());
        }
        $this->assertSame(5, $p->stockOf($own->fresh()));

        // İkinci partiya başqa maya ilə — orta maya: (5 × 25 + 5 × 35) / 10 = 30
        $p->produce($own, 5, today()->toDateString(), [], 175, 'Başlanğıc qalıq', 7);
        $this->assertEqualsWithDelta(30, (float) $own->fresh()->avg_cost, 0.0001);
        $this->assertSame(10, $p->stockOf($own->fresh()));
    }

    public function test_order_takes_from_own_stock_and_cancellation_returns_it(): void
    {
        $p = app(OwnProduction::class);
        $own = OwnProduct::create(['product_id' => 5, 'product_variant_id' => 9]);
        $p->produce($own, 3, today()->toDateString(), [], 60, null, 7); // 20 AZN / şüşə

        $order = $this->order(2);
        $part = $p->allocate($order, $order->items()->first(), 7);
        $this->assertSame(2, (int) $part->quantity);
        $this->assertSame('20.00', $part->unit_cost);
        $this->assertFalse($part->fresh()->courier_pays);
        $this->assertSame(OrderItemAllocation::RESERVED, $part->fresh()->status); // bildirmə mərhələsi yoxdur
        $this->assertTrue($part->warehouse->is_own);
        $this->assertSame(1, $p->stockOf($own));
        // Öz anbar depo borcu yaratmır
        $this->assertArrayNotHasKey($part->warehouse_id, app(FinanceService::class)->warehouseDebts()->all());

        // Stokda 1 qalıb, sifariş 3 istəyir — yalnız 1 götürülür, 2 partiya gözləyir
        $second = $this->order(3);
        $this->assertSame(1, (int) $p->allocate($second, $second->items()->first(), 7)->quantity);
        $this->assertSame(0, $p->stockOf($own));
        $this->assertSame(['orders' => 1, 'quantity' => 2], $p->waiting()[$own->id]);
        try {
            $p->allocate($second, $second->items()->first(), 7);
            $this->fail('Stok bitib');
        } catch (ValidationException) {
        }
        $low = $p->lowStock();
        $this->assertCount(1, $low);
        $this->assertSame(0, $low[0]['stock']);

        // Birinci sifariş ləğv olunur — 2 şüşə anbara qayıdır
        app(\App\Services\ProcurementService::class)->cancelAllocation($order, $part->id, 'test', 7);
        $this->assertSame(2, $p->stockOf($own));

        // Götürülüb, sonra qapıda imtina → "qaytarılır" (hələ kuryerdə), "qaytarıldı" olanda anbara düşür
        $part2 = OrderItemAllocation::where('order_item_id', $second->items()->first()->id)->first();
        $part2->update(['status' => OrderItemAllocation::RETURNING]);
        $this->assertSame(2, $p->stockOf($own));
        $part2->update(['status' => OrderItemAllocation::RETURNED]);
        $this->assertSame(3, $p->stockOf($own));
    }

    public function test_profit_report_does_not_count_own_production_cost_twice(): void
    {
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('credit_applications', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->decimal('total', 12, 2)->default(0); $t->decimal('bank_fee_rate', 6, 2)->nullable(); $t->timestamps(); });
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'cash']);
        $wh = \App\Models\Procurement\Warehouse::create(['name_az' => 'Depo A']);
        $ownProduct = OwnProduct::create(['product_id' => 5, 'product_variant_id' => 9]);
        app(OwnProduction::class)->produce($ownProduct, 1, today()->toDateString(), [], 20, null, 7);
        $deliver = function (float $total, float $courierFee, ?int $warehouse) {
            $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 1, 'total' => $total, 'courier_fee' => $courierFee]);
            $item = $order->items()->create(['product_id' => 5, 'product_variant_id' => 9, 'quantity' => 1]);
            $warehouse
                ? app(\App\Services\ProcurementService::class)->recordDirect($order, $item->id, $warehouse, '50', 7)
                : app(OwnProduction::class)->allocate($order, $item, 7);
            $order->forceFill(['order_status_id' => 17])->save();
            DB::table('order_status_logs')->insert(['order_id' => $order->id, 'status_id' => 17, 'created_at' => now(), 'updated_at' => now()]);
        };
        $deliver(100, 10, $wh->id); // depodan: satış 90 (10 — kuryerin), maya 50
        $deliver(60, 0, null);      // öz istehsal (maya 20): xammal kimi xərclərdədir — burada sayılmır
        $f = app(FinanceService::class);
        $cash = $f->system('cash');
        $expenseCat = \App\Models\Finance\FinanceCategory::where('type', 'expense')->value('id');
        $raw = \App\Models\Finance\FinanceCategory::where('code', 'raw_material')->value('id');
        foreach ([['outgo', 30, $expenseCat], ['supplier_payment', 40, $raw], ['warehouse_payment', 50, null]] as [$kind, $amount, $cat]) {
            MoneyMovement::create(['from_account_id' => $cash->id, 'to_account_id' => $cash->id, 'amount' => $amount, 'kind' => $kind, 'category_id' => $cat, 'occurred_at' => now()]);
        }

        $r = app(\App\Services\Finance\ProfitReport::class)->build(now()->startOfMonth(), now()->endOfMonth());
        $k = now()->format('Y-m');
        $this->assertSame(15000, $r['sales'][$k]);
        $this->assertSame(2, $r['orders'][$k]);
        $this->assertSame(5000, $r['cost'][$k]);
        $this->assertSame(7000, $r['expense'][$k]); // depoya ödəniş xərc deyil
        $this->assertSame(3000, $r['net'][$k]);

        // Pul axınında depoya ödəniş "Mal alışı"ndadır, xərclərdə yox
        $flow = app(\App\Services\Finance\CashFlowReport::class)->build(now()->startOfMonth(), now()->endOfMonth(), $cash, false);
        $this->assertSame(5000, $flow['totals']['purchase'][$k]);
        $this->assertSame(7000, $flow['totals']['expense'][$k]);
    }

    public function test_menu_and_permission_exist(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.production.products'));
        $menu = collect(config('admin_menu'))->flatMap(fn ($i) => $i['children'] ?? [])->flatMap(fn ($i) => $i['children'] ?? [$i]);
        $this->assertTrue($menu->contains(fn ($i) => ($i['route'] ?? null) === 'admin.production.products' && ($i['permission'] ?? null) === 'production'));
    }
}
