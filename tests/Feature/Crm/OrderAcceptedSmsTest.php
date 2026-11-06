<?php

namespace Tests\Feature\Crm;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Services\BonusService;
use App\Services\Crm\OrderAcceptedSms;
use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** CRM-də operator sifariş yaradanda "qəbul olundu" SMS-i: şablon aktivdirsə, {bonus} — təhvildə yazılacaq bonus */
class OrderAcceptedSmsTest extends TestCase
{
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('customers', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('surname')->default(''), $t->string('mobile')->nullable(), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->string('order_no'), $t->unsignedBigInteger('customer_id')->nullable(), $t->unsignedBigInteger('order_status_id')->nullable(), $t->unsignedBigInteger('payment_method_id')->nullable(), $t->decimal('total', 12, 2)->default(0), $t->timestamps()]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name')->nullable(), $t->text('template'), $t->boolean('active')->default(1), $t->timestamps()]);
        DB::table('sms_templates')->insert(['code' => 'crm_order_accepted', 'template' => 'Hormetli {fullname}, sifarishiniz {order_no} qebul olundu. Bonus {bonus} AZN.', 'active' => 1]);

        $this->app->instance(SmsService::class, new class($this->sent) extends SmsService {
            public function __construct(private array &$log) {}
            public function send(string $number, string $message): ?string { $this->log[] = [$number, $message]; return 'ok'; }
        });
        $this->mock(BonusService::class, fn ($m) => $m->shouldReceive('pendingFor')->andReturn(7.5));
    }

    private function order(?string $mobile = '994501234567'): Order
    {
        $customer = Customer::forceCreate(['name' => 'Aysel', 'surname' => 'Məmmədova', 'mobile' => $mobile]);

        return Order::forceCreate(['order_no' => 'PS261106000001', 'customer_id' => $customer->id, 'total' => 150]);
    }

    public function test_sends_when_template_active(): void
    {
        $this->assertTrue(app(OrderAcceptedSms::class)->send($this->order()));
        $this->assertSame([['994501234567', 'Hormetli Aysel Məmmədova, sifarishiniz PS261106000001 qebul olundu. Bonus 7.50 AZN.']], $this->sent);
    }

    public function test_nothing_when_template_off_or_no_mobile(): void
    {
        $this->assertNull(app(OrderAcceptedSms::class)->send($this->order(null)));
        DB::table('sms_templates')->update(['active' => 0]);
        $this->assertNull(app(OrderAcceptedSms::class)->send($this->order()));
        $this->assertSame([], $this->sent);
    }
}
