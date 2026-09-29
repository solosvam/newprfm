<?php

namespace Tests\Feature\Procurement;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never migrate or refresh the developer's database. Legacy base tables are not in migrations.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
        });
        Schema::create('order_statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name_az')->nullable();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->integer('customer_id');
            $t->unsignedBigInteger('order_status_id');
            $t->string('order_no')->default('TEST');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->integer('quantity');
            $t->integer('product_id')->nullable();
            $t->integer('product_variant_id')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->integer('size_id')->nullable();
        });
        Schema::create('sizes', function (Blueprint $t) {
            $t->id();
            $t->string('name_az');
        });
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->up();
        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'new', 'name_az' => 'Sifariş verildi']);
    }

    private function order(int $quantity = 2): Order
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $order->items()->create(['quantity' => $quantity]);

        return $order;
    }

    private function offer(Order $order, Warehouse $warehouse, int $quantity, string $price, ?int $itemId = null)
    {
        $service = app(ProcurementService::class);
        $service->createRequests($order, [$warehouse->id], [$itemId ?? $order->items()->first()->id], 7);
        $requestItem = WarehouseRequestItem::latest('id')->first();

        return $service->recordOffer($order, $requestItem->id, ['available_quantity' => $quantity, 'unit_cost' => $price, 'source' => 'phone'], 7);
    }

    public function test_split_two_units_between_warehouses_and_keep_cost_184(): void
    {
        $order = $this->order();
        $second = $order->items()->create(['quantity' => 1]);
        $a = Warehouse::create(['name_az' => 'Anbar 1']);
        $b = Warehouse::create(['name_az' => 'Anbar 2']);
        $c = Warehouse::create(['name_az' => 'Anbar 3']);
        $service = app(ProcurementService::class);
        $service->allocate($order, $this->offer($order, $c, 1, '50')->id, 1, 7);
        $service->allocate($order, $this->offer($order, $b, 2, '54')->id, 1, 7);
        $service->allocate($order, $this->offer($order, $a, 1, '80', $second->id)->id, 1, 7);
        $this->assertSame(3, OrderItemAllocation::count());
        $this->assertEquals(184, OrderItemAllocation::get()->sum(fn ($a) => $a->quantity * $a->unit_cost));
        $this->assertSame(3, DB::table('allocation_status_logs')->count());
        $this->assertSame(1, $order->fresh()->order_status_id);
    }

    public function test_cannot_overallocate_order_quantity(): void
    {
        $order = $this->order(1);
        $service = app(ProcurementService::class);
        $a = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $b = $this->offer($order, Warehouse::create(['name_az' => 'B']), 1, '60');
        $service->allocate($order, $a->id, 1, 7);
        $this->expectException(ValidationException::class);
        $service->allocate($order, $b->id, 1, 7);
    }

    public function test_cannot_overallocate_warehouse_offer(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($order, $offer->id, 2, 7);
    }

    public function test_cannot_allocate_offer_from_another_order(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($this->order(), $offer->id, 1, 7);
    }

    public function test_cancel_preserves_history_and_releases_quantity(): void
    {
        $order = $this->order(1);
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $service = app(ProcurementService::class);
        $allocation = $service->allocate($order, $offer->id, 1, 7);
        $service->cancelAllocation($order, $allocation->id, 'Başqa anbar seçiləcək', 7);
        $service->cancelAllocation($order, $allocation->id, 'Təkrar klik', 7);
        $this->assertSame(2, $allocation->logs()->count());
        $service->allocate($order, $offer->id, 1, 7);
        $this->assertSame(2, OrderItemAllocation::count());
        $this->assertSame(1, OrderItem::first()->quantity);
    }

    public function test_updated_offer_does_not_change_selected_price_and_old_offer_cannot_be_selected(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 2, '50');
        $service = app(ProcurementService::class);
        $allocation = $service->allocate($order, $offer->id, 1, 7);
        $service->recordOffer($order, $offer->warehouse_request_item_id, ['available_quantity' => 2, 'unit_cost' => '55', 'source' => 'phone'], 7);
        $this->assertSame('50.00', $allocation->fresh()->unit_cost);
        $this->assertSame(2, $offer->requestItem->offers()->count());
        $this->expectException(ValidationException::class);
        $service->allocate($order, $offer->id, 1, 7);
    }

    public function test_batch_request_is_atomic_when_an_item_belongs_to_another_order(): void
    {
        $order = $this->order();
        $other = $this->order();
        try {
            app(ProcurementService::class)->createRequests($order, [Warehouse::create(['name_az' => 'A'])->id], [$other->items()->first()->id], 7);
            $this->fail('Invalid item accepted');
        } catch (ValidationException $e) {
            $this->assertSame(0, DB::table('warehouse_requests')->count());
        }
    }

    public function test_unavailable_answer_does_not_require_price(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 0, '0');
        $this->assertNull($offer->unit_cost);
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($order, $offer->id, 1, 7);
    }

    public function test_completed_order_cannot_be_modified(): void
    {
        $order = $this->order();
        DB::table('order_statuses')->where('id', 1)->update(['code' => 'delivered']);
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->createRequests($order, [Warehouse::create(['name_az' => 'A'])->id], [$order->items()->first()->id], 7);
    }

    public function test_schema_rollback_removes_only_new_tables(): void
    {
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->down();
        $this->assertFalse(Schema::hasTable('warehouses'));
        $this->assertTrue(Schema::hasTable('orders'));
    }

    public function test_repeated_selection_request_is_not_duplicated(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 2, '50');
        $key = (string) Str::uuid();
        $service = app(ProcurementService::class);
        $first = $service->allocate($order, $offer->id, 1, 7, $key);
        $second = $service->allocate($order, $offer->id, 1, 7, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderItemAllocation::count());
    }

    public function test_panel_renders_existing_responses_and_allocations(): void
    {
        $user = new User(['name' => 'Operator', 'surname' => 'Test']);
        $user->id = 7;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'Anbar A']), 2, '50');
        app(ProcurementService::class)->allocate($order, $offer->id, 1, 7);
        $this->get(route('admin.procurement.show', $order))->assertOk()->assertSee('Anbar A')->assertSee('50.00');
        $this->get(route('admin.procurement.warehouses'))->assertOk()->assertSee('Anbar A');
        $this->get(route('admin.procurement.warehouses.edit', Warehouse::first()))->assertOk()->assertSee('Anbar A');
    }

    public function test_employee_without_crm_permission_cannot_access_or_write(): void
    {
        $user = new User(['name' => 'Kuryer']);
        $user->id = 9;
        Gate::before(fn () => false);
        $this->actingAs($user, 'admin');
        $this->get(route('admin.procurement.warehouses'))->assertForbidden();
        $this->post(route('admin.procurement.warehouses.store'), ['name_az' => 'Anbar', 'active' => 1])->assertForbidden();
        $this->assertSame(0, Warehouse::count());
    }

    public function test_admin_authentication_required(): void
    {
        $this->get('/admin/procurement/warehouses')->assertRedirect(route('admin.login.form'));
    }
}
