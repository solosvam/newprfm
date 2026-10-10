<?php

namespace Tests\Feature\Courier;

use App\Models\Order\Order;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use App\Services\ProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

class CourierFlowTest extends TestCase
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
            $t->string('payment_status')->default('cod'); $t->unsignedBigInteger('courier_id')->nullable();
            foreach (['discount', 'referral_discount', 'delivery_fee', 'gift_wrap_fee', 'total'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->integer('product_id')->nullable(); $t->integer('product_variant_id')->nullable();
            $t->decimal('unit_price', 12, 2)->default(0); $t->decimal('list_price', 12, 2)->nullable();
            $t->integer('quantity'); $t->integer('cancelled_quantity')->default(0); $t->decimal('total', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('name'); });
        // "Təhvil verildi" referal bonusunu yoxlayır (ReferralSettings) — proqram söndürülüdür
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id'); $t->string('provider');
            $t->string('provider_order_id')->nullable(); $t->decimal('amount', 12, 2); $t->string('status'); $t->timestamps();
        });
        foreach ([
            '2026_09_27_210000_create_payment_operations_and_saved_cards.php',
            '2026_09_29_150000_create_procurement_tables.php',
            '2026_09_29_190000_add_supply_flow_to_order_item_allocations.php',
            '2026_09_29_200000_create_finance_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->orderStatusFixtures();
    }

    /** 1 ədəd, qapıda nağd 120 AZN; anbar seçilib, kuryer təyin olunub */
    private function assigned(User $courier): array
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'total' => 120, 'payment_status' => 'cod']);
        $order->items()->create(['quantity' => 1]);
        $wh = Warehouse::create(['name_az' => 'A']);
        $p = app(ProcurementService::class);
        $p->createRequests($order, [$wh->id], [$order->items()->first()->id], 7);
        $offer = $p->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '80', 'source' => 'phone'], 7);
        $part = $p->allocate($order, $offer->id, 1, 7);
        $order->forceFill(['courier_id' => $courier->id])->save();
        app(OrderStatusService::class)->set($order->fresh(), 'courier_assigned', 7);

        return [$order->fresh(), $part, $wh];
    }

    public function test_full_courier_flow_with_cash(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part, $wh] = $this->assigned($courier);
        $s = app(OrderStatusService::class);
        $f = app(FinanceService::class);

        $this->assertNotNull($s->deliveryBlock($order)); // hələ götürülməyib
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        // Kuryer anbara öz pulundan ödəyir
        $f->record($f->courierAccount($courier), $f->warehouseAccount($wh), 80, 'warehouse_payment', ['order_item_allocation_id' => $part->id], $courier->id);
        $this->assertNull($s->deliveryBlock($order->fresh()));

        $s->startDelivery($order, $courier->id);
        $s->arrive($order, $courier->id);
        $s->arrive($order, $courier->id); // təkrar — yeni qeyd yox
        $this->assertSame(1, DB::table('order_status_logs')->where('order_id', $order->id)->where('status_id', 16)->count());

        try {
            $s->deliver($order, $courier->id, 100); // səhv məbləğ
            $this->fail('Məbləğ fərqli olmamalı idi');
        } catch (ValidationException) {
        }
        $s->deliver($order, $courier->id, 120);

        $this->assertSame('delivered', $this->statusCode($order));
        $this->assertSame('paid', $order->fresh()->payment_status);
        // Kuryer: +120 müştəridən, −80 anbara → 40 təhvil verməlidir
        $this->assertSame(4000, $f->balances()[$f->courierAccount($courier)->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);
    }

    public function test_customer_gets_one_sms_when_the_courier_starts_delivery(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->string('mobile')->nullable(); $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        (require database_path('migrations/2026_09_21_150000_create_sms_templates_table.php'))->up();
        (require database_path('migrations/2026_09_30_100000_create_sms_logs_and_warehouse_sms.php'))->up();
        (require database_path('migrations/2026_10_10_180000_add_delivery_status_to_sms_logs.php'))->up();
        (require database_path('migrations/2026_10_10_200000_sms_templates_name_variable.php'))->up();
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel', 'surname' => 'Quliyeva', 'mobile' => '0501234567', 'bonus_balance' => 7.5]);
        config(['services.parfumshop_sms.login' => 'login', 'services.parfumshop_sms.password' => 'secret']);
        \Illuminate\Support\Facades\Http::fake(['apps.lsim.az/*' => \Illuminate\Support\Facades\Http::response(['obj' => 9, 'errorCode' => 0])]);

        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part] = $this->assigned($courier);
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $s = app(OrderStatusService::class);

        $s->startDelivery($order, $courier->id);
        $s->startDelivery($order, $courier->id); // təkrar klik

        $log = \App\Models\SmsLog::where('context', 'order_sent')->sole();
        $this->assertSame('994501234567', preg_replace('/\D/', '', '994'.ltrim($log->msisdn, '0')));
        $this->assertStringContainsString('Hormetli Aysel,', $log->message);
        $this->assertStringContainsString('120.00', $log->message);
        $this->assertSame((string) $order->id, (string) $log->subject_id);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_returning_part_keeps_warehouse_debt_until_returned(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part, $wh] = $this->assigned($courier);
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $f = app(FinanceService::class);
        $f->warehouseAccount($wh);
        $this->assertSame(8000, $f->warehouseDebts()[$wh->id]);

        $part->fresh()->update(['status' => 'returning']);   // qapıda imtina — məhsul hələ kuryerdədir
        $this->assertSame(8000, $f->warehouseDebts()[$wh->id]);
        $part->fresh()->update(['status' => 'returned']);    // anbara qaytarıldı
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);
    }

    public function test_warehouse_refund_after_return_restores_courier_balance(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part, $wh] = $this->assigned($courier);
        $f = app(FinanceService::class);
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $f->record($f->courierAccount($courier), $f->warehouseAccount($wh), 80, 'warehouse_payment', ['order_item_allocation_id' => $part->id], $courier->id);
        $this->assertSame(-8000, $f->balances()[$f->courierAccount($courier)->id]); // şirkət kuryerə borcludur

        // Mal qaytarılmayıbsa anbardan pul geri alınmır
        try {
            $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 80, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);
            $this->fail('Qaytarılmamış mala görə geri ödəniş olmamalı idi');
        } catch (ValidationException) {
        }

        $part->fresh()->update(['status' => 'returned']);
        // Qaytarıldı, pul hələ alınmayıb: anbar şirkətə 80 borcludur
        $this->assertSame(-8000, $f->warehouseDebts()[$wh->id]);

        $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 80, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);

        $this->assertSame(0, $f->balances()[$f->courierAccount($courier)->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);
        $this->assertSame(0, $f->allocationPaid([$part->id])[$part->id]);
        // Ödənilən qədərdən çox geri alınmır
        $this->expectException(ValidationException::class);
        $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 1, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);
    }

    public function test_courier_can_transfer_order_before_picking(): void
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('active')->default(true));
        \Spatie\Permission\Models\Role::create(['name' => FinanceService::COURIER_ROLE, 'guard_name' => 'admin']);
        [$a, $b] = [User::forceCreate(['name' => 'Anar']), User::forceCreate(['name' => 'Bəxtiyar'])];
        $a->assignRole(FinanceService::COURIER_ROLE);
        $b->assignRole(FinanceService::COURIER_ROLE);
        [$order, $part] = $this->assigned($a);
        $s = app(OrderStatusService::class);

        $this->assertNull($s->transferBlock($order));
        $this->assertSame(['Bəxtiyar'], $s->couriers()->reject(fn ($u) => $u->id === $a->id)->pluck('name')->all());
        try {
            $s->transferCourier($order, $a, $a->id); // özünə
            $this->fail('Özünə ötürmək olmaz');
        } catch (ValidationException) {
        }
        $s->transferCourier($order, $s->couriers()->firstWhere('id', $b->id), $a->id); // controller kimi — bazadan
        $this->assertSame($b->id, (int) $order->fresh()->courier_id);
        $this->assertSame('courier_assigned', $this->statusCode($order));
        $this->assertStringContainsString('→ Bəxtiyar', DB::table('order_status_logs')->where('kind', 'courier_change')->value('note'));

        // Artıq A-nın sifarişi deyil; B götürəndən sonra ötürmək olmaz
        try {
            $s->transferCourier($order, $a, $a->id);
            $this->fail('Başqasının sifarişi');
        } catch (HttpException) {
        }
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $b->id);
        $this->assertStringContainsString('götürülüb', (string) $s->transferBlock($order->fresh()));
    }

    public function test_delivery_problem_is_marked_as_notice(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part] = $this->assigned($courier);
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $s = app(OrderStatusService::class);
        $s->startDelivery($order, $courier->id);
        $s->deliveryProblem($order, $courier->id, 'Müştəri qapını açmır');
        $this->assertSame('Çatdırılma problemi: Müştəri qapını açmır', DB::table('order_status_logs')->where('kind', 'delivery_problem')->value('note'));
    }

    public function test_other_courier_cannot_touch_the_order(): void
    {
        [$order] = $this->assigned(User::forceCreate(['name' => 'A']));
        $this->expectException(HttpException::class);
        app(OrderStatusService::class)->startDelivery($order, User::forceCreate(['name' => 'B'])->id);
    }

    public function test_online_paid_order_collects_nothing(): void
    {
        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part] = $this->assigned($courier);
        $order->forceFill(['payment_status' => 'paid'])->save();
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $s = app(OrderStatusService::class);
        $s->startDelivery($order, $courier->id);
        $s->deliver($order->fresh(), $courier->id, null);

        $this->assertSame('delivered', $this->statusCode($order));
        $this->assertSame(0, app(FinanceService::class)->balances()[app(FinanceService::class)->courierAccount($courier)->id] ?? 0);
    }
}
