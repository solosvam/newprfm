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
            '2026_11_12_140000_add_opening_debt_to_warehouses.php',
            '2026_09_29_190000_add_supply_flow_to_order_item_allocations.php',
            '2026_11_02_100000_add_courier_pays_to_order_item_allocations.php',
            '2026_09_29_200000_create_finance_tables.php',
            '2026_10_16_100000_create_finance_categories.php',
            '2026_11_07_160000_create_courier_locations.php',
            '2026_11_07_180000_add_pick_photo_to_order_item_allocations.php',
            '2026_11_07_200000_create_courier_notifications.php',
            '2026_11_07_210000_courier_expenses.php',
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
        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.courier_group_id' => '-407456641']);
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
        // Təyin / ötürmə — yalnız kuryerlərin qrupuna, kuryerin adı ilə; şəxsi Telegram-a getmir
        $this->assertSame(0, \App\Models\SmsLog::where('context', 'courier_order')->count());
        $sent = \App\Models\SmsLog::where('context', 'courier_group')->sole();
        $this->assertSame(['-407456641', 'sent', 'telegram'], [$sent->msisdn, $sent->status, $sent->channel]);
        $this->assertSame('🔁 Anar, sizə sifariş ötürüldü: PS1 (Rauf ötürdü)', $sent->message);
        $request = collect(\Illuminate\Support\Facades\Http::recorded())->first()[0]->data();
        $this->assertSame('Sifarişi aç', $request['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame(route('kuryer.order', $order), $request['reply_markup']['inline_keyboard'][0][0]['url']);

        // Anarda 50 AZN şirkət pulu var
        $f->record($f->system('cash'), $f->courierAccount($anar), 50, 'courier_advance', [], 7);

        $this->actingAs($anar, 'admin')->get(route('kuryer.team'))->assertForbidden(); // adi kuryer
        $this->actingAs($head, 'admin')->get(route('kuryer.team'))->assertOk()->assertSee('Anar')->assertSee('50.00 AZN');
        $this->get(route('kuryer.team.show', $anar))->assertOk()->assertSee('Hesabıma köçür')->assertSee('PS1');

        $this->post(route('kuryer.team.collect', $anar), ['amount' => 60])->assertSessionHasErrors('amount');
        $this->post(route('kuryer.team.collect', $head), ['amount' => 1])->assertSessionHasErrors('amount'); // özündən
        $this->post(route('kuryer.team.collect', $anar), ['amount' => 30, 'note' => 'axşam'])->assertSessionHas('success');

        $balances = $f->balances();
        $this->assertSame(2000, (int) $balances[$f->courierAccount($anar)->id]);
        $this->assertSame(3000, (int) $balances[$f->courierAccount($head)->id]);
        $this->assertSame('courier_collect', \App\Models\Finance\MoneyMovement::latest('id')->value('kind'));
        $collect = \App\Models\SmsLog::where('context', 'courier_collect')->sole();
        $this->assertStringContainsString('Rauf balansınızdan 30.00 AZN götürdü', $collect->message);
        $this->assertStringContainsString('Qalıq: 20.00 AZN', $collect->message);

        // Filtrlər: tarixçə növə görə (qalıq bütün tarixçə üzrə qalır), arxiv statusa / axtarışa görə
        $html = $this->get(route('kuryer.team.show', ['courier' => $anar, 'h_kind' => 'courier_advance']))->assertOk()->getContent();
        $this->assertStringContainsString('Avans / qaytarma aldı', $html);
        $this->assertStringNotContainsString('Baş kuryerə təhvil —', $html);
        $this->assertStringContainsString('1 hərəkət', $html);
        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => DB::table('order_statuses')->where('code', 'delivered')->value('id')]);
        $this->get(route('kuryer.team.show', ['courier' => $anar, 'a_status' => 'delivered', 'a_q' => 'PS1']))->assertOk()->assertSee('1 sifariş · 120.00 AZN');
        $this->get(route('kuryer.team.show', ['courier' => $anar, 'a_q' => 'yoxdur']))->assertOk()->assertSee('Filtrə uyğun sifariş yoxdur.');
        // Baş kuryer: filtrsiz — yalnız bu gün; filtrdə ən çox 3 gün geriyə (daha əvvəlki tarix yazsa da); admin hamısını görür
        DB::table('orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(2)]);
        $this->get(route('kuryer.team.show', $anar))->assertOk()->assertSee('0 sifariş · 0.00 AZN')->assertSee('Bu günün siyahısı.');
        $this->get(route('kuryer.team.show', ['courier' => $anar, 'a_from' => now()->subDays(10)->toDateString()]))
            ->assertOk()->assertSee('1 sifariş · 120.00 AZN')->assertSee('Ən çox 3 gün geriyə')->assertSee('min="'.today()->subDays(3)->toDateString().'"', false);
        DB::table('orders')->where('id', $order->id)->update(['updated_at' => now()->subDays(5)]);
        $this->get(route('kuryer.team.show', ['courier' => $anar, 'a_from' => now()->subDays(10)->toDateString()]))->assertOk()->assertSee('0 sifariş · 0.00 AZN');
        $admin = User::forceCreate(['name' => 'Admin']);
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'courier.team', 'guard_name' => 'admin']));
        $this->actingAs($admin, 'admin')->get(route('admin.courier.team.show', $anar))->assertOk()->assertSee('1 sifariş · 120.00 AZN')->assertDontSee('Bu günün siyahısı');
        $this->actingAs($head, 'admin');
        DB::table('orders')->where('id', $order->id)->update(['updated_at' => now()]);

        // Anarın öz tarixçəsində
        $this->actingAs($anar, 'admin')->get(route('kuryer.account'))->assertOk()->assertSee('Baş kuryerə təhvil');

        // Baş kuryer Anarın sifarişini (heç nə götürülməyib) Vüsala ötürür — hər ikisinə Telegram
        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => DB::table('order_statuses')->where('code', 'courier_assigned')->value('id')]);
        $vusal = User::forceCreate(['name' => 'Vüsal', 'telegram_id' => '222']);
        $vusal->assignRole(FinanceService::COURIER_ROLE);
        $this->actingAs($anar, 'admin')->post(route('kuryer.team.transfer', [$anar, $order]), ['courier_id' => $vusal->id])->assertForbidden();
        $this->actingAs($head, 'admin')->get(route('kuryer.team.show', $anar))->assertSee('Başqa kuryerə ötür');
        $this->post(route('kuryer.team.transfer', [$anar, $order]), ['courier_id' => $anar->id])->assertSessionHasErrors('status');
        $this->post(route('kuryer.team.transfer', [$anar, $order]), ['courier_id' => $vusal->id])->assertSessionHas('success');
        $this->assertSame($vusal->id, (int) $order->fresh()->courier_id);
        $this->assertStringContainsString('Baş kuryer (Rauf) sifarişi ötürdü: Anar → Vüsal', DB::table('order_status_logs')->where('kind', 'courier_change')->latest('id')->value('note'));
        $this->assertSame('🔁 Vüsal, sizə sifariş ötürüldü: PS1 (Anar ötürdü)', \App\Models\SmsLog::where('context', 'courier_group')->latest('id')->value('message'));
        // Sifariş götürülən kuryerə — şəxsi
        $this->assertSame(['111' => '↪️ Rauf PS1 sifarişini sizdən Vüsal adlı kuryerə ötürdü'], \App\Models\SmsLog::where('context', 'courier_order')->pluck('message', 'msisdn')->all());
        // Artıq Anarın sifarişi deyil
        $this->post(route('kuryer.team.transfer', [$anar, $order]), ['courier_id' => $head->id])->assertNotFound();
    }

    /** Kuryer tətbiqinin səhifələri üçün cədvəllər, "Kuryer" rolu; kuryeri qaytarır */
    private function courierAppTables(): User
    {
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('users', fn (Blueprint $t) => [$t->boolean('active')->default(true), $t->string('telegram_id')->nullable()]);
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

        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        (require database_path('migrations/2026_11_07_140000_add_location_to_users.php'))->up();
        Schema::create('genders', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('product_genders', fn (Blueprint $t) => [$t->integer('product_id'), $t->integer('gender_id')]);
        Schema::table('products', fn (Blueprint $t) => $t->integer('type_id')->nullable());

        return $courier;
    }

    /**
     * Xərc: kuryer yazır (balansdan dərhal çıxır, "Kuryer xərcləri"), qəbz şəkli istəyə görə; adminə zəng, baş kuryerə bildiriş.
     * Ləğv: baş kuryer — başqasının son 3 gündəki xərcini (öz xərcini yox), admin — istənilənini; kuryerin özü — yox.
     */
    public function test_courier_expenses_and_cancellation(): void
    {
        $courier = $this->courierAppTables();
        (require database_path('migrations/2026_10_29_130000_create_admin_notifications.php'))->up();
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        (require database_path('migrations/2026_11_01_140000_head_courier.php'))->up();
        $head = User::forceCreate(['name' => 'Rauf']);
        $head->assignRole(FinanceService::HEAD_COURIER_ROLE);
        $f = app(FinanceService::class);
        $f->record($f->system('cash'), $f->courierAccount($courier), 50, 'courier_advance', [], 7);
        $balance = fn (User $u) => (int) $f->balances()[$f->courierAccount($u)->id];
        $this->actingAs($courier, 'admin');

        $this->get(route('kuryer.account'))->assertOk()->assertSee('Xərc yaz')->assertSee('placeholder="Məsələn: Taxi"', false);
        $this->post(route('kuryer.expenses.store'), ['amount' => 5])->assertSessionHasErrors('note');
        $this->post(route('kuryer.expenses.store'), ['amount' => 0, 'note' => 'Taxi'])->assertSessionHasErrors('amount');
        $this->post(route('kuryer.expenses.store'), ['amount' => 5, 'note' => ' Taxi ', 'photo' => \Illuminate\Http\UploadedFile::fake()->image('q.jpg', 2000, 3000)])
            ->assertRedirect(route('kuryer.account').'#expenses')->assertSessionHas('success', '5.00 AZN xərc yazıldı.');
        $this->post(route('kuryer.expenses.store'), ['amount' => 70, 'note' => 'Yemək'])->assertSessionHas('success'); // balansdan çox — şirkət borclu qalır

        $taxi = \App\Models\Finance\MoneyMovement::where('kind', 'courier_expense')->where('note', 'Taxi')->sole();
        $this->assertSame(-2500, $balance($courier)); // 50 − 5 − 70
        $this->assertSame('courier_expense', $taxi->category->code);
        $this->assertSame('Kuryer xərci', $taxi->kindLabel());
        $photo = \App\Services\Courier\PickPhotoStorage::path($taxi->photo, \App\Services\Courier\PickPhotoStorage::EXPENSES);
        $this->assertNotNull($photo);
        $this->assertSame([853, 1280], array_slice(getimagesize($photo), 0, 2)); // 1280 px-ə kiçildilib
        $this->assertFalse(\App\Models\Finance\FinanceCategory::where('code', 'courier_expense')->sole()->fits('outgo')); // "Yeni əməliyyat"da seçilmir

        // Kuryer öz xərclərini və qəbzini görür, ləğv edə bilmir
        $this->get(route('kuryer.account'))->assertSee('Taxi')->assertSee(route('kuryer.expenses.photo', $taxi), false)->assertDontSee('Ləğv et');
        $this->get(route('kuryer.expenses.photo', $taxi))->assertOk();
        $this->post(route('kuryer.team.expense-cancel', [$courier, $taxi]))->assertForbidden();
        $this->actingAs($head, 'admin')->get(route('kuryer.expenses.photo', $taxi))->assertNotFound(); // başqasının "öz" linki

        // Bildirişlər: adminə zəng, baş kuryerə tətbiqdə
        $bell = DB::table('admin_notifications')->where('type', 'courier_expense')->where('subject_id', $taxi->id)->sole();
        $this->assertSame('Fərid · 5.00 AZN · Taxi', $bell->body);
        $this->assertSame(route('admin.courier.team.show', $courier, false).'#expenses', $bell->url);
        $notice = \App\Models\Courier\CourierNotification::where('user_id', $head->id)->where('type', 'expense_added')->first();
        $this->assertSame(['Fərid: 5.00 AZN xərc', 'Taxi'], [$notice->title, $notice->body]);

        // Baş kuryer: görür, qəbzə baxır, ləğv edir — pul kuryerə qayıdır, kuryerə bildiriş
        $this->get(route('kuryer.team.show', $courier))->assertOk()->assertSee('Taxi')->assertSee('Ləğv et');
        $this->get(route('kuryer.team.expense-photo', [$courier, $taxi]))->assertOk();
        $this->post(route('kuryer.team.expense-cancel', [$courier, $taxi]))->assertSessionHas('success');
        $this->assertSame(-2000, $balance($courier));
        $this->post(route('kuryer.team.expense-cancel', [$courier, $taxi]))->assertSessionHasErrors('expense'); // ikinci dəfə yox
        $this->assertSame('expense_cancelled', \App\Models\Courier\CourierNotification::where('user_id', $courier->id)->latest('id')->value('type'));
        $this->actingAs($courier, 'admin')->get(route('kuryer.account'))->assertSee('ləğv edilib — Rauf')->assertSee('Xərc ləğv edildi: Taxi');

        // Baş kuryer öz xərcini ləğv edə bilmir; 3 gündən köhnə xərci də — admin edə bilər
        $own = app(\App\Services\Courier\CourierExpenses::class)->record($head, 3, 'Taxi');
        $this->actingAs($head, 'admin')->post(route('kuryer.team.expense-cancel', [$head, $own]))->assertSessionHasErrors('expense');
        $food = \App\Models\Finance\MoneyMovement::where('note', 'Yemək')->sole();
        $food->forceFill(['occurred_at' => now()->subDays(4)])->save();
        $this->post(route('kuryer.team.expense-cancel', [$courier, $food]))->assertSessionHasErrors('expense');
        $this->post(route('kuryer.team.expense-cancel', [$head, $taxi]))->assertNotFound(); // başqa kuryerin xərci
        $admin = User::forceCreate(['name' => 'Admin']);
        $admin->givePermissionTo('courier.team');
        $this->actingAs($admin, 'admin')->get(route('admin.courier.team.show', $courier))->assertOk()
            ->assertSee('Yemək')->assertSee(route('admin.courier.team.expense-cancel', [$courier, $food]), false);
        $this->post(route('admin.courier.team.expense-cancel', [$courier, $food]))->assertSessionHas('success');
        $this->post(route('admin.courier.team.expense-cancel', [$head, $own]))->assertSessionHas('success');

        $this->assertSame(5000, $balance($courier));
        $this->assertSame(0, $balance($head));

        // Admin kuryerin balansından birbaşa xərc yazır ("finance" icazəsi ilə) — kuryerin xərclərinə düşür, kuryerə bildiriş
        $this->post(route('admin.courier.team.expense-store', $courier), ['amount' => 4, 'note' => 'Benzin'])->assertForbidden();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'finance', 'guard_name' => 'admin']);
        $admin->givePermissionTo('finance');
        $this->get(route('admin.courier.team.show', $courier))->assertSee('Xərc yaz');
        $before = $balance($courier);
        $this->post(route('admin.courier.team.expense-store', $courier), ['amount' => 4, 'note' => 'Benzin'])
            ->assertRedirect(route('admin.courier.team.show', $courier).'#expenses')->assertSessionHas('success');
        $this->assertSame($before - 400, $balance($courier));
        $benzin = \App\Models\Finance\MoneyMovement::where('note', 'Benzin')->sole();
        $this->assertSame(['courier_expense', $admin->id], [$benzin->kind, (int) $benzin->created_by]);
        $this->assertSame('expense_written', \App\Models\Courier\CourierNotification::where('user_id', $courier->id)->latest('id')->value('type'));
        $this->post(route('admin.courier.team.expense-store', $courier), ['amount' => 4])->assertSessionHasErrors('note', null, 'expense');

        // Maliyyə → Məxaric kuryerin balansından — kuryerin "Xərclər"ində də görünür, kuryerə bildiriş; baş kuryer ləğv edə bilmir
        $fuel = \App\Models\Finance\FinanceCategory::create(['name' => 'Yanacaq', 'type' => 'expense']);
        $this->post(route('admin.finance.store'), ['kind' => 'outgo', 'account_id' => $f->courierAccount($courier)->id, 'category_id' => $fuel->id, 'amount' => 6, 'note' => 'Avqust'])
            ->assertSessionHas('success');
        $this->assertSame($before - 1000, $balance($courier));
        $outgo = \App\Models\Finance\MoneyMovement::where('kind', 'outgo')->sole();
        $this->assertSame(['expense_written', 'Yanacaq · Avqust · Admin yazdı'], [
            \App\Models\Courier\CourierNotification::where('user_id', $courier->id)->latest('id')->value('type'),
            \App\Models\Courier\CourierNotification::where('user_id', $courier->id)->latest('id')->value('body')]);
        $this->actingAs($courier, 'admin')->get(route('kuryer.account'))->assertSee('Yanacaq · Avqust')->assertSee('Benzin');
        $this->assertFalse(app(\App\Services\Courier\CourierExpenses::class)->canCancel($outgo, $head));
        $this->assertTrue(app(\App\Services\Courier\CourierExpenses::class)->canCancel($outgo, $admin));

        \App\Services\Courier\PickPhotoStorage::delete($taxi->photo, \App\Services\Courier\PickPhotoStorage::EXPENSES);
    }

    /** Sifariş kuryerə təyin olunanda push (OneSignal, "user-{id}") — Telegram ilə yanaşı */
    public function test_courier_gets_push_when_order_is_assigned(): void
    {
        config(['services.onesignal' => ['app_id' => 'app-1', 'rest_api_key' => 'key-1']]);
        \Illuminate\Support\Facades\Http::fake(['api.onesignal.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'n1'])]);
        $first = $this->courierAppTables();
        [$order] = $this->assigned($first);
        $courier = User::forceCreate(['name' => 'Vüsal']);
        $courier->assignRole(FinanceService::COURIER_ROLE);
        app(OrderStatusService::class)->assignCourier($order, $courier->fresh(), 7); // operator kuryeri dəyişir

        $sent = collect(\Illuminate\Support\Facades\Http::recorded())->map(fn ($pair) => $pair[0]->data())
            ->first(fn ($data) => ($data['include_aliases']['external_id'] ?? null) === ['user-'.$courier->id]);
        $this->assertNotNull($sent, 'kuryerə push getməlidir');
        $this->assertSame('Yeni sifariş: PS1', $sent['headings']['az']);
        $this->assertSame(['badge' => 1], $sent['data']); // ikonun rəqəmi push-un özündə
        // Push-a basanda bildiriş oxunmuş olur və sifariş açılır
        $notice = \App\Models\Courier\CourierNotification::where('user_id', $courier->id)->sole();
        $this->assertSame(route('kuryer.notifications.open', $notice), $sent['url']);
        $this->assertSame(['order_assigned', route('kuryer.order', $order, false)], [$notice->type, $notice->url]);

        // Tətbiqdə: OneSignal qurulubsa — SDK və kuryerin xarici ID-si
        $html = $this->actingAs($courier, 'admin')->get(route('kuryer.home'))->getContent();
        $this->assertStringContainsString('data-push-user="user-'.$courier->id.'"', $html);
        $this->assertStringContainsString('OneSignalSDK.page.js', $html);
    }

    /** Tətbiq açılanda kuryerin yeri yazılır; admin Kuryerlər səhifəsində görür */
    public function test_courier_location_is_saved_and_shown_to_admin(): void
    {
        $courier = $this->courierAppTables();
        $this->actingAs($courier, 'admin');
        $this->assertStringContainsString('class="geo-gate"', $this->get(route('kuryer.home'))->getContent());

        $this->postJson(route('kuryer.location'), ['lat' => 200, 'lng' => 49.8])->assertUnprocessable();
        $this->postJson(route('kuryer.location'), ['lat' => 40.4000, 'lng' => 49.8500])->assertOk();
        $this->postJson(route('kuryer.location'), ['lat' => 40.4093, 'lng' => 49.8671, 'accuracy' => 12.4])->assertOk();
        $this->assertSame(2, \App\Models\Courier\CourierLocation::where('user_id', $courier->id)->count()); // tarixçə
        $fresh = $courier->fresh();
        $this->assertEquals([40.4093, 49.8671, 12], [(float) $fresh->location_lat, (float) $fresh->location_lng, (int) $fresh->location_accuracy]);
        $this->assertTrue($fresh->location_at->isToday());

        $admin = User::forceCreate(['name' => 'Admin']);
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'courier.team', 'guard_name' => 'admin']));
        $html = $this->actingAs($admin, 'admin')->get(route('admin.courier.team.show', $courier))->assertOk()
            ->assertSee('±12 m')->assertSee('2 nöqtə')->assertSee('Xəritədə bax')->getContent();
        // Marşrut admin panelin öz xəritəsində (courier-route.js) — xarici Google linki yoxdur
        $this->assertStringContainsString('data-courier-route', $html);
        $this->assertStringContainsString('backend/js/courier-route.js', $html);
        $this->assertMatchesRegularExpression('/data-points="\[\[40\.4,49\.85,&quot;\d\d:\d\d&quot;,null\],\[40\.4093,49\.8671,&quot;\d\d:\d\d&quot;,12\]\]"/', $html);
        $this->assertStringNotContainsString('google.com/maps', $html);
        $this->get(route('admin.courier.team.show', ['courier' => $courier, 'l_date' => today()->subDay()->toDateString()]))
            ->assertSee('Bu gün üçün yer məlumatı yoxdur.');
    }

    /** Bildirişlər: təyin olunanda yazılır; zəngdə oxunmamış sayı; açanda / sifarişi açanda oxunmuş; badge JSON; hamısını oxu */
    public function test_courier_notifications_unread_badge_and_read(): void
    {
        $first = $this->courierAppTables();
        [$order] = $this->assigned($first);
        $courier = User::forceCreate(['name' => 'Vüsal']);
        $courier->assignRole(FinanceService::COURIER_ROLE);
        app(OrderStatusService::class)->assignCourier($order, $courier->fresh(), 7);
        $other = \App\Models\Courier\CourierNotification::create(['user_id' => $courier->id, 'type' => 'money_collected',
            'title' => 'Rauf balansınızdan 10.00 AZN götürdü', 'url' => route('kuryer.account', [], false), 'created_at' => now()]);
        $this->actingAs($courier, 'admin');

        $this->get(route('kuryer.badge'))->assertExactJson(['unread' => 2]);
        $home = $this->get(route('kuryer.home'))->getContent();
        $this->assertStringContainsString('data-unread="2"', $home);
        $this->assertStringContainsString('<span class="bar__count">2</span>', $home);
        $this->get(route('kuryer.notifications'))->assertOk()->assertSee('Yeni sifariş: PS1')->assertSee('Hamısını oxunmuş et');

        // Sifarişi açmaq — onun bildirişi oxunmuş olur
        $this->get(route('kuryer.order', $order))->assertOk();
        $this->get(route('kuryer.badge'))->assertExactJson(['unread' => 1]);
        // Bildirişi açmaq — oxunmuş olur və öz yerinə keçir; başqasının bildirişi açılmır
        $this->get(route('kuryer.notifications.open', $other))->assertRedirect(route('kuryer.account'));
        $this->assertNotNull($other->fresh()->read_at);
        $this->actingAs($first, 'admin')->get(route('kuryer.notifications.open', $other))->assertNotFound();

        \App\Models\Courier\CourierNotification::create(['user_id' => $courier->id, 'type' => 'x', 'title' => 'T', 'created_at' => now()]);
        $this->actingAs($courier, 'admin')->post(route('kuryer.notifications.read-all'))->assertRedirect(route('kuryer.notifications'));
        $this->get(route('kuryer.badge'))->assertExactJson(['unread' => 0]);
        $this->assertStringNotContainsString('bar__count', $this->get(route('kuryer.home'))->getContent());
    }

    /** /kuryer: girişsiz — admin girişinə, kuryer admin panelə girəndə — tətbiqə, admin — tətbiqə yox */
    public function test_courier_app_access_and_redirects(): void
    {
        $courier = $this->courierAppTables();
        [$order] = $this->assigned($courier);

        $this->get(route('kuryer.home'))->assertRedirect(route('admin.login.form'));
        $this->actingAs($courier, 'admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('kuryer.home'));
        $this->get('/admin/orders')->assertRedirect(route('kuryer.home'));
        $this->get('/admin/courier/order/'.$order->id)->assertRedirect(route('kuryer.order', $order)); // köhnə Telegram linki
        $this->get(route('admin.logout'))->assertRedirect(route('admin.login.form'));            // çıxış toxunulmur

        $admin = User::forceCreate(['name' => 'Admin']);
        $this->actingAs($admin, 'admin')->get(route('kuryer.home'))->assertRedirect(route('admin.dashboard'));

        $sw = $this->get(route('kuryer.sw'))->assertOk();
        $this->assertSame('/kuryer', $sw->headers->get('Service-Worker-Allowed'));
        $this->assertStringContainsString('application/javascript', $sw->headers->get('Content-Type'));
    }

    /** Tətbiqin səhifələri: öz CSS / JS-i (admin panelin heç nəyi yüklənmir), aktiv sifariş, məhsulun şəkli */
    public function test_courier_app_pages(): void
    {
        $courier = $this->courierAppTables();
        [$order] = $this->assigned($courier);
        DB::table('types')->insert(['id' => 2, 'name_az' => 'Eau De Toilette']);
        DB::table('genders')->insert(['id' => 1, 'name_az' => 'Kişi üçün']);
        $productId = DB::table('products')->insertGetId(['name' => 'Sauvage', 'type_id' => 2]);
        DB::table('product_genders')->insert(['product_id' => $productId, 'gender_id' => 1]);
        $order->items()->update(['product_id' => $productId]);
        DB::table('product_images')->insert(['product_id' => $productId, 'image' => 'sauvage.jpg']);
        $this->actingAs($courier, 'admin');

        $home = $this->get(route('kuryer.home'))->assertOk()->getContent();
        $this->assertStringContainsString('courier-app/app.css', $home);
        $this->assertStringContainsString('courier-app/manifest.webmanifest', $home);
        $this->assertStringNotContainsString('backend/', $home);
        $this->assertStringContainsString(route('kuryer.order', $order), $home);
        $this->assertStringContainsString('class="gate"', $home); // telefonda brauzerdə — quraşdırma ekranı (app.css)
        $this->assertStringContainsString('data-splash', $home); // ilk açılış — splash
        $this->assertStringNotContainsString('data-splash', $this->withUnencryptedCookie('ps_k_splash', '1')->get(route('kuryer.home'))->getContent());
        $this->assertMatchesRegularExpression('/Aktiv<\/div>\s*<div class="stat__value accent">1</', $home);

        $page = $this->get(route('kuryer.order', $order))->assertOk()->getContent();
        $this->assertStringNotContainsString('backend/', $page);
        $this->assertStringContainsString('data-zoom="'.asset('frontend/uploads/products/sauvage.jpg').'"', $page);
        $this->assertStringContainsString('<div class="product__kind">Eau De Toilette · Kişi üçün</div>', $page); // növ və cins
        $this->assertStringContainsString(route('kuryer.pick', [$order, $order->items()->first()->allocations()->first()->id]), $page);

        $this->get(route('kuryer.account'))->assertOk()->assertSee('Haqq-hesab tarixçəsi')->assertSee(route('admin.logout'), false);
    }

    /** Operator depo üçün "pul vermə" seçdi: kuryer pulla bağlı heç nə görmür, yalnız "Götürdüm" — depoya borc qalır */
    public function test_courier_does_not_pay_the_warehouse_when_the_operator_says_so(): void
    {
        $courier = $this->courierAppTables();

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
        $html = $this->get(route('kuryer.order', $order))->assertOk()->getContent();
        $this->assertStringNotContainsString('Depoya ödəniş', $html);
        $this->assertStringNotContainsString('ödədim', $html);
        $this->assertStringContainsString('Götürdüm', $html);
        $this->assertStringNotContainsString('Operatorun qeydi', $html);
        // Operatorun kuryerə qeydi — kuryerin səhifəsində rəngli blokda
        $order->forceFill(['courier_note' => 'Saat 18:00-dan sonra çatdırın'])->save();
        $html = $this->get(route('kuryer.order', $order))->assertOk()->getContent();
        $this->assertStringContainsString('Operatorun qeydi', $html);
        $this->assertStringContainsString('Saat 18:00-dan sonra çatdırın', $html);
        $this->post(route('kuryer.pay', [$order, $part->id]), ['pick' => 1])->assertForbidden();

        // Götürmə şəkilsiz qəbul olunmur; şəkil 1280 px-ə kiçildilir, adminə icazə ilə verilir
        $this->post(route('kuryer.pick', [$order, $part->id]))->assertSessionHasErrors('photo');
        $this->assertSame('reserved', $part->fresh()->status === 'picked' ? 'picked' : 'reserved');
        $this->post(route('kuryer.pick', [$order, $part->id]), ['photo' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 3000, 2000)])
            ->assertSessionHas('success');
        $photo = \App\Services\Courier\PickPhotoStorage::path($part->fresh()->pick_photo);
        $this->assertNotNull($photo, 'şəkil saxlanmalıdır');
        $this->assertSame([1280, 853], array_slice(getimagesize($photo), 0, 2));
        $admin = User::forceCreate(['name' => 'Operator']);
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'crm', 'guard_name' => 'admin']));
        $this->actingAs($admin, 'admin')->get(route('admin.procurement.allocations.photo', [$order, $part->id]))->assertOk();
        $this->actingAs($courier, 'admin');
        \App\Services\Courier\PickPhotoStorage::delete($part->fresh()->pick_photo);
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

        $this->post(route('kuryer.pay', [$order, $part->id]), ['pick' => 1])->assertSessionHasErrors('photo'); // götürmə — şəkillə
        $this->assertSame(0, $f->allocationPaid([$part->id])[$part->id] ?? 0);
        $this->post(route('kuryer.pay', [$order, $part->id]), ['pick' => 1, 'photo' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 800, 600)])
            ->assertSessionHas('success', '80.00 AZN depoya ödəndi, götürüldü.');
        \App\Services\Courier\PickPhotoStorage::delete($part->fresh()->pick_photo);
        $this->assertSame('picked', $part->fresh()->status);
        $this->assertSame(8000, $f->allocationPaid([$part->id])[$part->id]);
        $this->assertSame(0, $f->warehouseDebts()[$wh->id]);

        // Təkrar basılsa ikinci dəfə ödənmir
        $this->post(route('kuryer.pay', [$order, $part->id]), ['pick' => 1])->assertSessionHas('success', 'Depoya artıq ödənilib, götürüldü.');
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
