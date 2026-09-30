<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentOperation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\ProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use OrderStatusFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name_az')->nullable(); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            foreach (['discount', 'delivery_fee', 'gift_wrap_fee', 'total'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->integer('product_id')->nullable(); $t->integer('product_variant_id')->nullable();
            $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('list_price', 12, 2)->nullable();
            $t->integer('quantity'); $t->integer('cancelled_quantity')->default(0); $t->decimal('total', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id'); $t->string('provider');
            $t->string('provider_order_id')->nullable(); $t->decimal('amount', 12, 2); $t->string('status'); $t->timestamps();
        });
        foreach ([
            '2026_09_27_210000_create_payment_operations_and_saved_cards.php',
            '2026_09_29_150000_create_procurement_tables.php',
            '2026_09_29_150000_create_payment_items_table.php',
            '2026_09_29_190000_add_supply_flow_to_order_item_allocations.php',
            '2026_09_29_200000_create_finance_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->orderStatusFixtures();
    }

    private function f(): FinanceService { return app(FinanceService::class); }

    /** 1 ədəd, anbar A, 50 AZN — seçilib */
    private function allocation(): array
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $order->items()->create(['quantity' => 1]);
        $warehouse = Warehouse::create(['name_az' => 'Anbar A']);
        $procurement = app(ProcurementService::class);
        $procurement->createRequests($order, [$warehouse->id], [$order->items()->first()->id], 7);
        $offer = $procurement->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '50', 'source' => 'phone'], 7);

        return [$order, $procurement->allocate($order, $offer->id, 1, 7), $warehouse];
    }

    private function courier(): FinanceAccount
    {
        return $this->f()->courierAccount(User::forceCreate(['name' => 'Fərid', 'surname' => 'M.']));
    }

    public function test_warehouse_debt_appears_when_picked_and_courier_payment_moves_balances(): void
    {
        [$order, $allocation, $warehouse] = $this->allocation();
        $whAccount = $this->f()->warehouseAccount($warehouse);
        app(ProcurementService::class)->transition($order, $allocation->id, 'reserved', [], 7);
        $this->assertSame(0, $this->f()->warehouseDebts()[$warehouse->id]);

        app(ProcurementService::class)->transition($order, $allocation->id, 'picked', [], 7);
        $this->assertSame(5000, $this->f()->warehouseDebts()[$warehouse->id]);

        $courier = $this->courier();
        $this->f()->record($courier, $whAccount, 30, 'warehouse_payment', ['order_item_allocation_id' => $allocation->id], 7);

        $this->assertSame(2000, $this->f()->warehouseDebts()[$warehouse->id]);
        $this->assertSame(-3000, $this->f()->balances()[$courier->id]); // şirkət kuryerə borcludur
        $this->assertSame(3000, $this->f()->allocationPaid([$allocation->id])[$allocation->id]);
        $this->assertSame($order->id, MoneyMovement::first()->order_id);

        $this->expectException(ValidationException::class); // qalıq 20-dir
        $this->f()->record($courier, $whAccount, 25, 'warehouse_payment', ['order_item_allocation_id' => $allocation->id], 7);
    }

    public function test_courier_hands_cash_to_center_and_owner_debt(): void
    {
        $courier = $this->courier();
        $cash = $this->f()->system('cash');
        $this->f()->record($cash, $courier, 100, 'courier_advance', [], 7);
        $this->f()->record($courier, $cash, 60, 'courier_handover', [], 7);
        $this->assertSame(4000, $this->f()->balances()[$courier->id]);

        $owner = $this->f()->system('owner');
        $this->f()->record($owner, $this->f()->system('expense'), 500, 'expense', [], 7);
        $this->assertSame(-50000, $this->f()->balances()[$owner->id]); // şirkət sahibkara borcludur
    }

    public function test_kind_rules_are_enforced(): void
    {
        $this->expectException(ValidationException::class);
        // Xərc hesabından kassaya "köçürmə" olmaz
        $this->f()->record($this->f()->system('expense'), $this->f()->system('cash'), 10, 'transfer', [], 7);
    }

    public function test_reversal_restores_balances_once(): void
    {
        $cash = $this->f()->system('cash');
        $bank = $this->f()->system('bank');
        $m = $this->f()->record($bank, $cash, 200, 'transfer', [], 7);
        $r = $this->f()->reverse($m, 7, 'Səhv məbləğ');

        $this->assertSame(0, $this->f()->balances()[$cash->id]);
        $this->assertSame($m->id, $r->reversal_of_id);
        foreach ([$m, $r] as $again) {
            try {
                $this->f()->reverse($again, 7);
                $this->fail('Təkrar əks olunmamalı idi');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(2, MoneyMovement::count());
    }

    public function test_online_payment_and_refund_are_recorded_once(): void
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $payment = Payment::create(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'amount' => 300, 'status' => 'paid']);
        $this->f()->recordOnlinePayment($payment);
        $this->f()->recordOnlinePayment($payment);
        $op = PaymentOperation::create(['payment_id' => $payment->id, 'type' => 'refund', 'amount' => 100, 'status' => 'succeeded', 'idempotency_key' => 'k1']);
        $this->f()->recordOnlineRefund($op);
        $this->f()->recordOnlineRefund($op);

        $this->assertSame(2, MoneyMovement::count());
        $this->assertSame(20000, $this->f()->balances()[$this->f()->system('online')->id]);
    }

    public function test_order_settlements_tab_renders(): void
    {
        [$order, $allocation, $warehouse] = $this->allocation();
        app(ProcurementService::class)->transition($order, $allocation->id, 'picked', [], 7);
        $courier = $this->courier();
        $this->f()->record($courier, $this->f()->warehouseAccount($warehouse), 20, 'warehouse_payment', ['order_item_allocation_id' => $allocation->id], 7);
        $order->load(['items.allocations.warehouse']);
        $order->forceFill(['subtotal' => 100, 'discount' => 0]);
        $html = view('backend.crm.partials.order-settlements', ['order' => $order, 'settlement' => [
            'accounts' => FinanceAccount::whereIn('type', ['courier', 'cash'])->get(),
            'paid' => $this->f()->allocationPaid([$allocation->id]),
            'movements' => MoneyMovement::with(['from', 'to', 'user', 'reversedBy'])->where('order_id', $order->id)->get(),
        ]])->render();
        $this->assertStringContainsString('30.00 AZN', $html); // qalıq borc
        $this->assertStringContainsString('Anbara ödəniş', $html);
        $this->assertStringContainsString('Fərid', $html);
    }
}
