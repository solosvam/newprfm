<?php

namespace Tests\Feature\Payment;

use App\Http\Controllers\Frontend\PayLinkController;
use App\Models\Order\Order;
use App\Models\Setting;
use App\Services\OrderPayLinkService;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayLinkExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');

        // Sayt şablonunun ehtiyacı olan cədvəllər (SeoTest-dəki kimi)
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->string('image')->nullable(), $t->boolean('active')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->integer('brand_id')->nullable(), $t->boolean('active')]);
        Schema::create('categories', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable(), $t->string('slug'), $t->boolean('active')]);
        (require base_path('database/migrations/2026_09_21_130000_create_settings_table.php'))->up();
        (require base_path('database/migrations/2026_10_08_100000_create_popups_tables.php'))->up();
        (require base_path('database/migrations/2026_10_08_130000_create_pages_table.php'))->up();

        Schema::create('order_statuses', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('payment_methods', fn (Blueprint $t) => [$t->id(), $t->string('code')]);
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('mobile')->nullable(), $t->timestamps()]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name')->nullable(), $t->text('template'), $t->boolean('active')->default(1), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [
            $t->id(), $t->integer('customer_id'), $t->string('order_no')->default('PS777'), $t->string('pay_token', 16)->nullable(),
            $t->timestamp('pay_token_expires_at')->nullable(), $t->unsignedBigInteger('order_status_id'), $t->unsignedBigInteger('payment_method_id'),
            $t->string('payment_status')->default('pending'), $t->decimal('total', 12, 2)->default(100), $t->timestamps(),
        ]);
        Schema::create('order_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->integer('product_id')->nullable(), $t->integer('product_variant_id')->nullable(), $t->integer('quantity')->default(1), $t->decimal('unit_price', 12, 2)->default(0), $t->decimal('total', 12, 2)->default(0), $t->timestamps()]);
        Schema::create('order_item_cancellations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('order_id'), $t->decimal('amount', 12, 2)->default(0), $t->timestamps()]);
        Schema::create('payments', fn (Blueprint $t) => [$t->id(), $t->integer('customer_id'), $t->unsignedBigInteger('order_id'), $t->string('provider'), $t->string('status'), $t->timestamps()]);

        DB::table('order_statuses')->insert(['id' => 1, 'code' => 'new']);
        DB::table('payment_methods')->insert(['id' => 1, 'code' => 'card_online']);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Aysel Məmmədova', 'mobile' => '0501234567']);
        DB::table('orders')->insert(['id' => 1, 'customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function order(): Order
    {
        return Order::findOrFail(1);
    }

    public function test_new_link_gets_expiry_from_admin_setting(): void
    {
        $service = app(OrderPayLinkService::class);
        $this->assertSame(72, $service->hours());

        Setting::set('pay_link_hours', 5);
        $this->travelTo('2026-10-10 12:00:00');
        $service->ensureToken($order = $this->order());

        $this->assertSame('2026-10-10 17:00:00', $order->fresh()->pay_token_expires_at->toDateTimeString());
        $this->assertFalse($service->isExpired($order->fresh()));

        // Linki təkrar açmaq müddəti uzatmır
        $this->travelTo('2026-10-10 16:00:00');
        $service->url($order->fresh());
        $this->assertSame('2026-10-10 17:00:00', $order->fresh()->pay_token_expires_at->toDateTimeString());

        $this->travelTo('2026-10-10 17:00:01');
        $this->assertTrue($service->isExpired($order->fresh()));
    }

    public function test_sms_renews_expired_link_and_keeps_the_same_token(): void
    {
        $sent = [];
        $this->app->instance(SmsService::class, new class($sent) extends SmsService {
            public function __construct(private array &$sent) {}
            public function send(string $number, string $message): ?string { $this->sent[] = $message; return null; }
        });
        $service = app(OrderPayLinkService::class);

        $this->travelTo('2026-10-10 12:00:00');
        $token = $service->ensureToken($this->order());
        $this->travelTo('2026-10-20 12:00:00');
        $this->assertTrue($service->isExpired($this->order()));

        $service->sendSms($this->order());

        $order = $this->order();
        $this->assertSame($token, $order->pay_token);
        $this->assertSame('2026-10-23 12:00:00', $order->pay_token_expires_at->toDateTimeString());
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('/p/'.$token, $sent[0]);
    }

    public function test_expired_link_shows_no_order_data_and_cannot_start_payment(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $token = app(OrderPayLinkService::class)->ensureToken($this->order());
        $this->travelTo('2026-10-14 12:00:00');

        $this->get(route('pay.link', $token))->assertStatus(410)
            ->assertSee(__('paylink_expired'))
            ->assertDontSee('PS777')->assertDontSee('Aysel');

        $this->post(route('pay.link.start', $token))->assertRedirect(route('pay.link', $token));
        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_customer_returning_from_bank_sees_result_once_even_if_link_expired_meanwhile(): void
    {
        $this->travelTo('2026-10-10 12:00:00');
        $token = app(OrderPayLinkService::class)->ensureToken($this->order());
        $this->travelTo('2026-10-14 12:00:00');
        DB::table('orders')->update(['payment_status' => 'paid']);

        $this->withSession([PayLinkController::SESSION_RETURN_KEY.'1' => true])
            ->get(route('pay.link', $token))->assertOk()->assertSee('PS777');

        $this->get(route('pay.link', $token))->assertStatus(410);
    }
}
