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
}
