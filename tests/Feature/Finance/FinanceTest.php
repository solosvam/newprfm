<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\FinanceCategory;
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
            '2026_10_15_240000_convert_owner_to_person_accounts.php',
            '2026_10_16_100000_create_finance_categories.php',
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

    /** Borc günlər üzrə: dünən götürülən hissə qismən ödənilib, bu gün götürülən ödənilməyib; ümumi ödəniş ayrıca */
    public function test_warehouse_debt_is_split_by_pickup_day(): void
    {
        [$order, $yesterdayPart, $warehouse] = $this->allocation(); // 50 AZN
        $p = app(ProcurementService::class);
        $this->travelTo(now()->subDay());
        $p->transition($order, $yesterdayPart->id, 'picked', [], 7);
        $this->travelBack();

        $item = $order->items()->create(['quantity' => 1]);
        $p->createRequests($order, [$warehouse->id], [$item->id], 7);
        $offer = $p->recordOffer($order, WarehouseRequestItem::latest('id')->first()->id, ['available_quantity' => 1, 'unit_cost' => '35', 'source' => 'phone'], 7);
        $todayPart = $p->allocate($order, $offer->id, 1, 7);
        $p->transition($order, $todayPart->id, 'picked', [], 7);

        $account = $this->f()->warehouseAccount($warehouse);
        $this->f()->record($this->f()->system('cash'), $account, 20, 'warehouse_payment', ['order_item_allocation_id' => $yesterdayPart->id], 7);
        $this->f()->record($this->f()->system('cash'), $account, 5, 'warehouse_payment', [], 7); // ümumi ödəniş

        $byDay = $this->f()->warehouseDebtByDay($warehouse);

        $this->assertSame([[3500, 0, 3500], [5000, 2000, 3000]], array_map(fn ($d) => [$d['cost'], $d['paid'], $d['left']], $byDay['days']));
        $this->assertTrue($byDay['days'][0]['date']->isToday());
        $this->assertTrue($byDay['days'][1]['date']->isYesterday());
        $this->assertSame(500, $byDay['unlinked']);
        // Qalıqların cəmi − ümumi ödəniş = cari borc
        $this->assertSame(3500 + 3000 - 500, $this->f()->warehouseDebts()[$warehouse->id]);
    }

    public function test_courier_hands_cash_to_center(): void
    {
        $courier = $this->courier();
        $cash = $this->f()->system('cash');
        $this->f()->record($cash, $courier, 100, 'courier_advance', [], 7);
        $this->f()->record($courier, $cash, 60, 'courier_handover', [], 7);
        $this->assertSame(4000, $this->f()->balances()[$courier->id]);
    }

    public function test_income_outgo_need_fitting_category_and_persons_settle_by_transfer(): void
    {
        $cash = $this->f()->system('cash');
        $external = $this->f()->system('external');
        $owner = FinanceCategory::byCode('owner');
        $salary = FinanceCategory::create(['name' => 'Əməkhaqqı', 'type' => 'expense']);

        $in = $this->f()->record($external, $cash, 1000, 'income', ['category_id' => $owner->id], 7);   // sahibkar pul qoydu
        $this->f()->record($cash, $external, 300, 'outgo', ['category_id' => $salary->id], 7);
        $this->assertSame(70000, $this->f()->balances()[$cash->id]);
        $this->assertSame('Mədaxil · Sahibkar', $in->load('category')->label());

        // Şəxs: borc aldıq (Şəxs → Kassa) — qalıq mənfi; bir hissəsini qaytardıq
        $person = FinanceAccount::create(['type' => 'person', 'name' => 'Elçin']);
        $loan = $this->f()->record($person, $cash, 500, 'transfer', [], 7);
        $this->f()->record($cash, $person, 200, 'transfer', [], 7);
        $this->assertSame(-30000, $this->f()->balances()[$person->id]);
        $this->assertSame(FinanceCategory::byCode('transfer')->id, $loan->category_id);
        // Şəxs öz cibindən xərc ödədi — şirkətin ona borcu artır
        $this->f()->record($person, $external, 100, 'outgo', ['category_id' => $salary->id], 7);
        $this->assertSame(-40000, $this->f()->balances()[$person->id]);

        foreach ([
            fn () => $this->f()->record($external, $cash, 10, 'income', [], 7),                                   // kateqoriyasız
            fn () => $this->f()->record($external, $cash, 10, 'income', ['category_id' => $salary->id], 7),        // məxaric kateqoriyası
            fn () => $this->f()->record($cash, $external, 10, 'outgo', ['category_id' => FinanceCategory::byCode('sale')->id], 7), // avtomatik
            fn () => $this->f()->record($person, FinanceAccount::create(['type' => 'person', 'name' => 'B']), 10, 'transfer', [], 7), // şəxsdən şəxsə
        ] as $bad) {
            try {
                $bad();
                $this->fail('Qəbul olunmamalı idi');
            } catch (ValidationException) {
            }
        }
    }

    public function test_migration_turns_owner_into_category_and_expenses_into_outgo(): void
    {
        // Köhnə vəziyyət: sahibkar hesabı, "Xərclər" hesabı, xərc və sahibkar əməliyyatları
        (require database_path('migrations/2026_10_16_100000_create_finance_categories.php'))->down();
        DB::table('finance_accounts')->where('code', 'external')->update(['type' => 'expense', 'code' => 'expense', 'name' => 'Xərclər']);
        $owner = FinanceAccount::create(['type' => 'owner', 'code' => 'owner', 'name' => 'Sahibkar']);
        $cash = $this->f()->system('cash');
        $expense = FinanceAccount::where('code', 'expense')->first();
        $mv = fn ($from, $to, $kind) => MoneyMovement::create(['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 10, 'kind' => $kind, 'occurred_at' => now()])->id;
        $contribution = $mv($owner, $cash, 'owner_contribution');
        $repayment = $mv($cash, $owner, 'owner_repayment');
        $spent = $mv($cash, $expense, 'expense');
        (require database_path('migrations/2026_10_16_100000_create_finance_categories.php'))->up();

        $this->assertNull(FinanceAccount::find($owner->id)); // başqa hərəkəti yoxdur — silinir
        $external = $this->f()->system('external');
        $this->assertSame(['income', $external->id, 'owner'], [MoneyMovement::find($contribution)->kind, MoneyMovement::find($contribution)->from_account_id, MoneyMovement::find($contribution)->category->code]);
        $this->assertSame(['outgo', $external->id, 'owner'], [MoneyMovement::find($repayment)->kind, MoneyMovement::find($repayment)->to_account_id, MoneyMovement::find($repayment)->category->code]);
        $this->assertSame(['outgo', 'other_expense'], [MoneyMovement::find($spent)->kind, MoneyMovement::find($spent)->category->code]);
    }

    public function test_cash_flow_report_groups_by_category_and_month(): void
    {
        $cash = $this->f()->system('cash');
        $bank = $this->f()->system('bank');
        $external = $this->f()->system('external');
        $salary = FinanceCategory::create(['name' => 'Əməkhaqqı', 'type' => 'expense']);
        $bonus = FinanceCategory::create(['name' => 'Bonus', 'type' => 'expense', 'parent_id' => $salary->id]);
        $person = FinanceAccount::create(['type' => 'person', 'name' => 'Elçin']);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

        $this->f()->record($external, $cash, 1000, 'income', ['category_id' => FinanceCategory::byCode('owner')->id, 'occurred_at' => $lastMonth], 7);
        $this->f()->record($cash, $external, 300, 'outgo', ['category_id' => $salary->id], 7);
        $this->f()->record($cash, $external, 50, 'outgo', ['category_id' => $bonus->id], 7);
        $wrong = $this->f()->record($cash, $external, 20, 'outgo', ['category_id' => $salary->id], 7);
        $this->f()->reverse($wrong, 7);
        $this->f()->record($cash, $bank, 100, 'transfer', [], 7);                // daxili — nəticəyə təsir etmir
        $this->f()->record($person, $cash, 400, 'transfer', [], 7);              // borc — ayrıca blok
        $this->f()->record($person, $external, 30, 'outgo', ['category_id' => $salary->id], 7); // şəxs öz cibindən

        $report = app(\App\Services\Finance\CashFlowReport::class)->build(now()->subMonthNoOverflow()->startOfMonth(), now()->endOfMonth());
        [$prev, $cur] = [now()->subMonthNoOverflow()->format('Y-m'), now()->format('Y-m')];
        $row = fn ($section, $name) => collect($report[$section])->first(fn ($r) => $r['category']?->name === $name);

        $this->assertSame(38000, $row('expense', 'Əməkhaqqı')['values'][$cur]); // 300 + 50 bonus + 30 şəxsdən, 20 əks olunub
        $this->assertSame(5000, $row('expense', 'Bonus')['values'][$cur]);
        $this->assertSame(100000, $row('income', 'Sahibkar')['values'][$prev]);
        $this->assertNull($row('expense', 'Köçürmə'));                        // köçürmələr gizlədilib
        $this->assertSame(-38000, $report['totals']['result'][$cur]);
        $this->assertSame(40000, $report['persons']['in'][$cur]);

        $shown = app(\App\Services\Finance\CashFlowReport::class)->build(now()->startOfMonth(), now()->endOfMonth(), $cash, false);
        $this->assertSame(10000, collect($shown['expense'])->first(fn ($r) => $r['category']?->code === 'transfer')['values'][$cur]);
    }

    public function test_operations_page_filters_and_sums(): void
    {
        $cash = $this->f()->system('cash');
        $bank = $this->f()->system('bank');
        $external = $this->f()->system('external');
        $salary = FinanceCategory::create(['name' => 'Əməkhaqqı', 'type' => 'expense']);
        $bonus = FinanceCategory::create(['name' => 'Bonus', 'type' => 'expense', 'parent_id' => $salary->id]);
        $this->f()->record($external, $cash, 500, 'income', ['category_id' => FinanceCategory::byCode('adjustment')->id], 7);
        $this->f()->record($cash, $external, 40, 'outgo', ['category_id' => $salary->id, 'note' => 'Oktyabr maaşı'], 7);
        $this->f()->record($bank, $external, 10, 'outgo', ['category_id' => $bonus->id], 7);
        $wrong = $this->f()->record($cash, $external, 99, 'outgo', ['category_id' => $salary->id], 7);
        $this->f()->reverse($wrong, 7);
        $this->f()->record($cash, $bank, 100, 'transfer', [], 7);

        $data = fn (array $query) => app(\App\Http\Controllers\Backend\FinanceController::class)
            ->operations(\Illuminate\Http\Request::create('/', 'GET', $query), $this->f())->getData();
        $ids = fn (array $query) => collect($data($query)['movements']->items())->pluck('amount')->map(fn ($a) => (float) $a)->sort()->values()->all();

        // Əks olunanlar gizlidir; cəm = 500 − 40 − 10 (köçürmə daxil deyil)
        $all = $data([]);
        $this->assertSame(4, $all['movements']->total());
        $this->assertSame(450.0, $all['total']);
        $this->assertSame([10.0, 40.0], $ids(['type' => ['out']]));
        $this->assertSame([40.0, 100.0, 500.0], $ids(['account' => [$cash->id]]));
        $this->assertSame([10.0, 40.0], $ids(['category' => [$salary->id]]));       // alt kateqoriya da daxildir
        $this->assertSame([40.0], $ids(['q' => 'maaş']));
        // Əks olunanlar göstəriləndə: əsli və əksi; Məxaric filtrində əks əməliyyat (mədaxil) yoxdur
        $this->assertSame([10.0, 40.0, 99.0], $ids(['type' => ['out'], 'reversed' => 1]));
        $this->assertSame(450.0, $data(['reversed' => 1])['total']);
        $this->assertSame(0, $data(['from' => now()->addDays(2)->toDateString()])['movements']->total());
    }

    public function test_kind_rules_are_enforced(): void
    {
        $this->expectException(ValidationException::class);
        // Kənar hesabdan kassaya "köçürmə" olmaz (bu, mədaxildir)
        $this->f()->record($this->f()->system('external'), $this->f()->system('cash'), 10, 'transfer', [], 7);
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
