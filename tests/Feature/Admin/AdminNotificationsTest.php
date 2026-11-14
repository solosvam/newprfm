<?php

namespace Tests\Feature\Admin;

use App\Models\Order\Order;
use App\Services\Admin\AdminNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]); // icazəni Gate::before yoxlayır
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->timestamps()]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name_az')->nullable()]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->nullable(), $t->string('mobile')->nullable(), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->string('order_no'), $t->integer('customer_id')->nullable(), $t->integer('payment_method_id')->nullable(),
            $t->boolean('one_click')->default(false), $t->string('guest_mobile')->nullable(), $t->decimal('total')->default(0), $t->timestamps()]);
        (require database_path('migrations/2026_10_29_130000_create_admin_notifications.php'))->up();

        DB::table('users')->insert(['id' => 7, 'name' => 'Operator']);
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'cash', 'name_az' => 'Qapıda ödəniş'], ['id' => 4, 'code' => 'installment', 'name_az' => 'Hissə-hissə']]);
        DB::table('customers')->insert(['id' => 5, 'name' => 'Aysel', 'surname' => 'Məmmədova', 'mobile' => '994501234567']);
    }

    private function order(array $attrs): Order
    {
        return Order::create($attrs + ['order_no' => 'PS'.random_int(1000, 9999), 'customer_id' => 5, 'payment_method_id' => 1, 'total' => 120]);
    }

    public function test_types_dedupe_feed_and_read(): void
    {
        $notifier = app(AdminNotifier::class);
        $site = $this->order([]);
        $notifier->order($site);
        $notifier->order($site); // təkrar — ikinci bildiriş yoxdur
        $notifier->order($this->order(['payment_method_id' => 4]));
        $notifier->order($this->order(['one_click' => true, 'customer_id' => null, 'guest_mobile' => '994551112233']));

        $this->assertSame(['site_order', 'credit', 'one_click'], DB::table('admin_notifications')->orderBy('id')->pluck('type')->all());
        $this->assertSame('/admin/crm/customer/5/order/'.$site->id, DB::table('admin_notifications')->where('type', 'site_order')->value('url'));

        Gate::before(fn () => true);
        $this->actingAs(\App\Models\User::find(7), 'admin');
        $feed = $this->getJson('/admin/notifications')->assertOk()->json();
        $this->assertSame(3, $feed['unread']);
        $this->assertSame('Yeni asan sifariş', $feed['items'][0]['title']);
        $this->assertTrue($feed['items'][0]['unread']);

        $this->postJson('/admin/notifications/read')->assertOk();
        $this->assertSame(0, $this->getJson('/admin/notifications')->json('unread'));
    }

    public function test_requires_crm_permission(): void
    {
        $this->getJson('/admin/notifications')->assertUnauthorized();
        Gate::before(fn () => false);
        $this->actingAs(\App\Models\User::find(7), 'admin');
        $this->getJson('/admin/notifications')->assertForbidden();
    }

    public function test_new_site_order_goes_to_operators_telegram_group_once(): void
    {
        Schema::table('orders', fn (Blueprint $t) => [$t->string('payment_status')->default('cod'), $t->integer('customer_address_id')->nullable(), $t->text('customer_note')->nullable()]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->integer('order_id'), $t->integer('product_id')->nullable(), $t->integer('product_variant_id')->nullable(),
            $t->integer('quantity')->default(1), $t->decimal('unit_price', 12, 2)->default(0), $t->timestamps()]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->integer('brand_id')->nullable()]);
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->integer('size_id')->nullable()]);
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('customer_addresses', fn (Blueprint $t) => [$t->id(), $t->boolean('on_call')->default(false)]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name')->nullable(), $t->text('template')->nullable(), $t->boolean('active')->default(1), $t->timestamps()]);
        (require database_path('migrations/2026_09_30_100000_create_sms_logs_and_warehouse_sms.php'))->up();
        (require database_path('migrations/2026_10_10_180000_add_delivery_status_to_sms_logs.php'))->up();
        (require database_path('migrations/2026_10_15_160000_create_telegram_messages.php'))->up();
        DB::table('brands')->insert(['id' => 1, 'name' => 'Dior']);
        DB::table('products')->insert(['id' => 1, 'name' => 'Sauvage', 'brand_id' => 1]);
        DB::table('sizes')->insert(['id' => 1, 'name_az' => '100 ml']);
        DB::table('product_variants')->insert(['id' => 1, 'size_id' => 1]);

        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
        config(['services.telegram.bot_token' => 'x', 'services.telegram.orders_group_id' => '-3762730899']);
        $order = $this->order(['total' => 189, 'customer_note' => 'Axşam zəng edin']);
        DB::table('order_items')->insert(['order_id' => $order->id, 'product_id' => 1, 'product_variant_id' => 1, 'quantity' => 1, 'unit_price' => 189]);

        $telegram = app(\App\Services\Telegram\OrderTelegram::class);
        $log = $telegram->newOrder($order);
        $this->assertSame('sent', $log->status);
        $this->assertSame('-3762730899', $log->msisdn);
        $text = $telegram->text($order->fresh());
        $this->assertStringContainsString('Yeni sifariş</b> · '.$order->order_no, $text);
        $this->assertStringContainsString('Aysel Məmmədova · 994501234567', $text);
        $this->assertStringContainsString('• Dior Sauvage 100 ml × 1 — 189.00 AZN', $text);
        $this->assertStringContainsString('189.00 AZN</b> · Qapıda ödəniş', $text);
        $this->assertStringContainsString('Axşam zəng edin', $text);
        $this->assertNull($telegram->newOrder($order)); // ikinci dəfə getmir

        // Asan sifariş (qonaq)
        $easy = $this->order(['one_click' => true, 'customer_id' => null, 'guest_mobile' => '994551112233']);
        $this->assertStringContainsString('Asan sifariş</b>', $telegram->text($easy));
        $this->assertStringContainsString('Qonaq · 994551112233', $telegram->text($easy));
        \Illuminate\Support\Facades\Http::assertSentCount(1);

        // Qrup yazılmayıbsa — heç nə
        config(['services.telegram.orders_group_id' => '']);
        $this->assertNull($telegram->newOrder($easy));
    }
}
