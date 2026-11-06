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
            '2026_11_02_100000_add_courier_pays_to_order_item_allocations.php',
            '2026_09_29_200000_create_finance_tables.php',
            '2026_10_16_100000_create_finance_categories.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->orderStatusFixtures();
    }

    /** 1 ədəd, qapıda nağd 120 AZN; depo seçilib, kuryer təyin olunub */
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
        // Kuryer depoya öz pulundan ödəyir
        $f->record($f->courierAccount($courier), $f->warehouseAccount($wh), 80, 'warehouse_payment', ['order_item_allocation_id' => $part->id], $courier->id);
        $this->assertNull($s->deliveryBlock($order->fresh()));

        $s->startDelivery($order, $courier->id);
        $this->assertSame('sent', $this->statusCode($order)); // "Yoldayam"dan birbaşa "Təhvil verdim"

        try {
            $s->deliver($order, $courier->id, 100); // səhv məbləğ
            $this->fail('Məbləğ fərqli olmamalı idi');
        } catch (ValidationException) {
        }
        $s->deliver($order, $courier->id, 120);

        $this->assertSame('delivered', $this->statusCode($order));
        $this->assertSame('paid', $order->fresh()->payment_status);
        // Kuryer: +120 müştəridən, −80 depoya → 40 təhvil verməlidir
        $this->assertSame(4000, $f->balances()[$f->courierAccount($courier)->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);
    }

    public function test_no_sms_when_the_courier_starts_delivery(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->string('mobile')->nullable(); $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        (require database_path('migrations/2026_09_21_150000_create_sms_templates_table.php'))->up();
        (require database_path('migrations/2026_09_30_100000_create_sms_logs_and_warehouse_sms.php'))->up();
        (require database_path('migrations/2026_10_10_180000_add_delivery_status_to_sms_logs.php'))->up();
        (require database_path('migrations/2026_10_10_200000_sms_templates_name_variable.php'))->up();
        (require database_path('migrations/2026_11_01_130000_drop_order_sent_sms_template.php'))->up();
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel', 'surname' => 'Quliyeva', 'mobile' => '0501234567', 'bonus_balance' => 7.5]);
        config(['services.parfumshop_sms.login' => 'login', 'services.parfumshop_sms.password' => 'secret']);
        \Illuminate\Support\Facades\Http::fake(['apps.lsim.az/*' => \Illuminate\Support\Facades\Http::response(['obj' => 9, 'errorCode' => 0])]);

        $courier = User::forceCreate(['name' => 'Fərid']);
        [$order, $part] = $this->assigned($courier);
        app(ProcurementService::class)->transition($order, $part->id, 'picked', [], $courier->id);
        $s = app(OrderStatusService::class);

        $s->startDelivery($order, $courier->id);
        $s->startDelivery($order, $courier->id); // təkrar klik

        // "Yola çıxdım" SMS-i ləğv olunub (2026_11_01_130000) — yalnız status dəyişir
        $this->assertSame('sent', $this->statusCode($order));
        $this->assertSame(0, \App\Models\SmsLog::count());
        \Illuminate\Support\Facades\Http::assertNothingSent();
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
        $part->fresh()->update(['status' => 'returned']);    // depoya qaytarıldı
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

        // Mal qaytarılmayıbsa depodan pul geri alınmır
        try {
            $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 80, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);
            $this->fail('Qaytarılmamış mala görə geri ödəniş olmamalı idi');
        } catch (ValidationException) {
        }

        $part->fresh()->update(['status' => 'returned']);
        // Qaytarıldı, pul hələ alınmayıb: depo şirkətə 80 borcludur
        $this->assertSame(-8000, $f->warehouseDebts()[$wh->id]);

        $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 80, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);

        $this->assertSame(0, $f->balances()[$f->courierAccount($courier)->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);
        $this->assertSame(0, $f->allocationPaid([$part->id])[$part->id]);
        // Ödənilən qədərdən çox geri alınmır
        $this->expectException(ValidationException::class);
        $f->record($f->warehouseAccount($wh), $f->courierAccount($courier), 1, 'warehouse_refund', ['order_item_allocation_id' => $part->id], $courier->id);
    }

    public function test_admin_bell_gets_warehouse_and_courier_events(): void
    {
        (require database_path('migrations/2026_10_29_130000_create_admin_notifications.php'))->up();
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        \Spatie\Permission\Models\Role::create(['name' => FinanceService::COURIER_ROLE, 'guard_name' => 'admin']);
        $courier = User::forceCreate(['name' => 'Fərid']);
        $courier->assignRole(FinanceService::COURIER_ROLE);
        [$order, $part] = $this->assigned($courier);
        $p = app(ProcurementService::class);
        $notifier = app(\App\Services\Admin\AdminNotifier::class);

        // Depo cavabı (link / Telegram bu metodu çağırır)
        $notifier->warehouseAnswered($part->offer);
        // Operator "rezerv" qeyd edir — bildiriş yox; depo özü (actor 0) — bildiriş
        $p->transition($order, $part->id, 'reserved', [], 0);
        $p->transition($order, $part->id, 'picked', [], $courier->id);
        $p->transition($order, $part->id, 'picked', [], $courier->id); // təkrar klik — ikinci bildiriş yox
        $s = app(OrderStatusService::class);
        $s->startDelivery($order, $courier->id);
        $s->deliver($order, $courier->id, 120);

        $rows = DB::table('admin_notifications')->orderBy('id')->get();
        $this->assertSame(['wh_answer', 'wh_reserved', 'courier_picked', 'courier_sent', 'courier_delivered'], $rows->pluck('type')->all());
        $this->assertSame('PS1 · A · var: 1 ədəd × 80.00 ₼', $rows[0]->body);
        $this->assertSame('PS1 · Fərid', $rows[4]->body);
        $this->assertSame(route('admin.crm.order', [1, $order->id], false), $rows[4]->url);
    }

    public function test_operator_reserve_does_not_notify(): void
    {
        (require database_path('migrations/2026_10_29_130000_create_admin_notifications.php'))->up();
        [$order, $part] = $this->assigned(User::forceCreate(['name' => 'Fərid']));
        app(ProcurementService::class)->transition($order, $part->id, 'reserved', [], 7);
        $this->assertSame(0, DB::table('admin_notifications')->count());
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

    /** Baş kuryer: kuryerlər səhifəsi, kuryerin pulunu özünə köçürmə; kuryerə Telegram — sifariş ötürüləndə və pul götürüləndə */
    public function test_head_courier_sees_team_collects_money_and_couriers_get_telegram(): void
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('active')->default(true));
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        (require database_path('migrations/2026_09_21_150000_create_sms_templates_table.php'))->up();
        (require database_path('migrations/2026_09_30_100000_create_sms_logs_and_warehouse_sms.php'))->up();
        (require database_path('migrations/2026_10_10_180000_add_delivery_status_to_sms_logs.php'))->up();
        (require database_path('migrations/2026_10_15_160000_create_telegram_messages.php'))->up();
        (require database_path('migrations/2026_11_01_140000_head_courier.php'))->up();
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('surname')->nullable(), $t->timestamps()]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->string('city')->nullable(), $t->string('address')->nullable(), $t->boolean('on_call')->default(false), $t->timestamps()]);
        Schema::table('orders', fn (Blueprint $t) => [$t->unsignedBigInteger('customer_address_id')->nullable(), $t->string('meeting_place')->nullable(), $t->boolean('gift_wrap')->default(false)]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel', 'surname' => 'Quliyeva']);
        \Spatie\Permission\Models\Role::create(['name' => FinanceService::COURIER_ROLE, 'guard_name' => 'admin']);
        config(['services.telegram.bot_token' => 'test-token']);
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);

        $anar = User::forceCreate(['name' => 'Anar', 'telegram_id' => '111']);
        $anar->assignRole(FinanceService::COURIER_ROLE);
        $head = User::forceCreate(['name' => 'Rauf']);
        $head->assignRole(FinanceService::HEAD_COURIER_ROLE);
        $s = app(OrderStatusService::class);
        $f = app(FinanceService::class);

        // Baş kuryer də kuryerdir: siyahıda var, sifarişi Anara ötürür → Anara Telegram
        $this->assertEqualsCanonicalizing(['Anar', 'Rauf'], $s->couriers()->pluck('name')->all());
        [$order] = $this->assigned($head);
        $s->transferCourier($order, $anar->fresh(), $head->id);
        $sent = \App\Models\SmsLog::where('context', 'courier_order')->sole();
        $this->assertSame(['111', 'sent', 'telegram'], [$sent->msisdn, $sent->status, $sent->channel]);
        $this->assertSame('🔁 Rauf sizə PS1 sifarişini ötürdü', $sent->message); // yalnız bildiriş — məlumat linkdədir
        $request = collect(\Illuminate\Support\Facades\Http::recorded())->first()[0]->data();
        $this->assertSame('Sifarişi aç', $request['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame(route('admin.courier.order', $order), $request['reply_markup']['inline_keyboard'][0][0]['url']);

        // Anarda 50 AZN şirkət pulu var
        $f->record($f->system('cash'), $f->courierAccount($anar), 50, 'courier_advance', [], 7);

        $this->actingAs($anar, 'admin')->get(route('admin.courier.team'))->assertForbidden(); // adi kuryer
        $this->actingAs($head, 'admin')->get(route('admin.courier.team'))->assertOk()->assertSee('Anar')->assertSee('50.00 AZN');
        $this->get(route('admin.courier.team.show', $anar))->assertOk()->assertSee('Hesabıma köçür')->assertSee('PS1');

        $this->post(route('admin.courier.team.collect', $anar), ['amount' => 60])->assertSessionHasErrors('amount');
        $this->post(route('admin.courier.team.collect', $head), ['amount' => 1])->assertSessionHasErrors('amount'); // özündən
        $this->post(route('admin.courier.team.collect', $anar), ['amount' => 30, 'note' => 'axşam'])->assertSessionHas('success');

        $balances = $f->balances();
        $this->assertSame(2000, (int) $balances[$f->courierAccount($anar)->id]);
        $this->assertSame(3000, (int) $balances[$f->courierAccount($head)->id]);
        $this->assertSame('courier_collect', \App\Models\Finance\MoneyMovement::latest('id')->value('kind'));
        $collect = \App\Models\SmsLog::where('context', 'courier_collect')->sole();
        $this->assertStringContainsString('Rauf balansınızdan 30.00 AZN götürdü', $collect->message);
        $this->assertStringContainsString('Qalıq: 20.00 AZN', $collect->message);

        // Filtrlər: tarixçə növə görə (qalıq bütün tarixçə üzrə qalır), arxiv statusa / axtarışa görə
        $html = $this->get(route('admin.courier.team.show', ['courier' => $anar, 'h_kind' => 'courier_advance']))->assertOk()->getContent();
        $this->assertStringContainsString('Avans / qaytarma aldı', $html);
        $this->assertStringNotContainsString('Baş kuryerə təhvil —', $html);
        $this->assertStringContainsString('1 hərəkət', $html);
        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => DB::table('order_statuses')->where('code', 'delivered')->value('id')]);
        $this->get(route('admin.courier.team.show', ['courier' => $anar, 'a_status' => 'delivered', 'a_q' => 'PS1']))->assertOk()->assertSee('1 sifariş · 120.00 AZN');
        $this->get(route('admin.courier.team.show', ['courier' => $anar, 'a_q' => 'yoxdur']))->assertOk()->assertSee('Filtrə uyğun sifariş yoxdur.');

        // Anarın öz tarixçəsində
        $this->actingAs($anar, 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('Baş kuryerə təhvil');

        // Baş kuryer Anarın sifarişini (heç nə götürülməyib) Vüsala ötürür — hər ikisinə Telegram
        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => DB::table('order_statuses')->where('code', 'courier_assigned')->value('id')]);
        $vusal = User::forceCreate(['name' => 'Vüsal', 'telegram_id' => '222']);
        $vusal->assignRole(FinanceService::COURIER_ROLE);
        $this->actingAs($anar, 'admin')->post(route('admin.courier.team.transfer', [$anar, $order]), ['courier_id' => $vusal->id])->assertForbidden();
        $this->actingAs($head, 'admin')->get(route('admin.courier.team.show', $anar))->assertSee('Başqa kuryerə ötür');
        $this->post(route('admin.courier.team.transfer', [$anar, $order]), ['courier_id' => $anar->id])->assertSessionHasErrors('status');
        $this->post(route('admin.courier.team.transfer', [$anar, $order]), ['courier_id' => $vusal->id])->assertSessionHas('success');
        $this->assertSame($vusal->id, (int) $order->fresh()->courier_id);
        $this->assertStringContainsString('Baş kuryer (Rauf) sifarişi ötürdü: Anar → Vüsal', DB::table('order_status_logs')->where('kind', 'courier_change')->latest('id')->value('note'));
        $messages = \App\Models\SmsLog::where('context', 'courier_order')->pluck('message', 'msisdn');
        $this->assertSame('🔁 Rauf Anar adlı kuryerin PS1 sifarişini sizə ötürdü', $messages['222']);
        $this->assertSame('↪️ Rauf PS1 sifarişini sizdən Vüsal adlı kuryerə ötürdü', $messages['111']);
        // Artıq Anarın sifarişi deyil
        $this->post(route('admin.courier.team.transfer', [$anar, $order]), ['courier_id' => $head->id])->assertNotFound();
    }

    /** Operator depo üçün "pul vermə" seçdi: kuryer pulla bağlı heç nə görmür, yalnız "Götürdüm" — depoya borc qalır */
    public function test_courier_does_not_pay_the_warehouse_when_the_operator_says_so(): void
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('users', fn (Blueprint $t) => [$t->boolean('active')->default(true), $t->string('telegram_id')->nullable()]);
        // Kuryerin sifariş səhifəsi üçün
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('surname')->nullable(), $t->string('mobile')->nullable(), $t->string('mobile_2')->nullable(), $t->timestamps()]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->string('city')->nullable(), $t->string('address')->nullable(), $t->boolean('on_call')->default(false), $t->timestamps()]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')->nullable()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->integer('size_id')->nullable()]);
        Schema::table('products', fn (Blueprint $t) => $t->integer('brand_id')->nullable());
        Schema::table('orders', fn (Blueprint $t) => [$t->unsignedBigInteger('customer_address_id')->nullable(), $t->unsignedBigInteger('payment_method_id')->nullable(),
            $t->string('meeting_place')->nullable(), $t->boolean('gift_wrap')->default(false), $t->text('customer_note')->nullable(), $t->text('courier_note')->nullable()]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel', 'mobile' => '0501234567']);
        \Spatie\Permission\Models\Role::create(['name' => FinanceService::COURIER_ROLE, 'guard_name' => 'admin']);
        $courier = User::forceCreate(['name' => 'Fərid']);
        $courier->assignRole(FinanceService::COURIER_ROLE);

        // Kuryer hələ təyin olunmayıb: depo seçilib → operator kuryeri "pul vermə" ilə təyin edir
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'total' => 120, 'payment_status' => 'cod']);
        $order->items()->create(['quantity' => 1]);
        $wh = Warehouse::create(['name_az' => 'A']);
        $p = app(ProcurementService::class);
        $p->createRequests($order, [$wh->id], [$order->items()->first()->id], 7);
        $offer = $p->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '80', 'source' => 'phone'], 7);
        $part = $p->allocate($order, $offer->id, 1, 7);
        $this->assertTrue($part->fresh()->courier_pays); // standart — köhnə qayda
        app(OrderStatusService::class)->assignCourier($order->fresh(), $courier->fresh(), 7, [$wh->id => false]);
        $this->assertFalse($part->fresh()->courier_pays);
        $this->assertSame('courier_assigned', $this->statusCode($order));

        $this->actingAs($courier, 'admin');
        $html = $this->get(route('admin.courier.order', $order))->assertOk()->getContent();
        $this->assertStringNotContainsString('Depoya ödəniş', $html);
        $this->assertStringNotContainsString('ödədim', $html);
        $this->assertStringContainsString('Götürdüm', $html);
        $this->assertStringNotContainsString('Operatorun qeydi', $html);
        // Operatorun kuryerə qeydi — kuryerin səhifəsində rəngli blokda
        $order->forceFill(['courier_note' => 'Saat 18:00-dan sonra çatdırın'])->save();
        $html = $this->get(route('admin.courier.order', $order))->assertOk()->getContent();
        $this->assertStringContainsString('Operatorun qeydi', $html);
        $this->assertStringContainsString('Saat 18:00-dan sonra çatdırın', $html);
        $this->post(route('admin.courier.pay', [$order, $part->id]), ['pick' => 1])->assertForbidden();

        $this->post(route('admin.courier.pick', [$order, $part->id]))->assertSessionHas('success');
        $f = app(FinanceService::class);
        $f->syncAccounts();
        $this->assertSame('picked', $part->fresh()->status);
        $this->assertSame(8000, $f->warehouseDebts()[$wh->id]);                         // depoya borc qalır
        $this->assertSame(0, (int) ($f->balances()[$f->courierAccount($courier)->id] ?? 0)); // kuryerin pulu ilə əlaqəsi yoxdur
    }

    /** "80.00 AZN ödədim, götürdüm": məbləğ yazılmır — qalıq ödənilir və götürülür; "Ödənişsiz götürdüm" — borc qalır, sonra "ödədim" */
    public function test_courier_pays_the_remaining_cost_and_picks_in_one_step(): void
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('active')->default(true));
        \Spatie\Permission\Models\Role::create(['name' => FinanceService::COURIER_ROLE, 'guard_name' => 'admin']);
        $courier = User::forceCreate(['name' => 'Fərid']);
        $courier->assignRole(FinanceService::COURIER_ROLE);
        [$order, $part, $wh] = $this->assigned($courier);
        $f = app(FinanceService::class);
        $this->actingAs($courier, 'admin');

        $this->post(route('admin.courier.pay', [$order, $part->id]), ['pick' => 1])->assertSessionHas('success', '80.00 AZN depoya ödəndi, götürüldü.');
        $this->assertSame('picked', $part->fresh()->status);
        $this->assertSame(8000, $f->allocationPaid([$part->id])[$part->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);

        // Təkrar basılsa ikinci dəfə ödənmir
        $this->post(route('admin.courier.pay', [$order, $part->id]), ['pick' => 1])->assertSessionHas('success', 'Depoya artıq ödənilib, götürüldü.');
        $this->assertSame(8000, $f->allocationPaid([$part->id])[$part->id]);
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
