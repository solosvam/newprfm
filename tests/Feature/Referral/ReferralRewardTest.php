<?php

namespace Tests\Feature\Referral;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerReferral;
use App\Models\Order\Order;
use App\Services\OrderStatusService;
use App\Services\Referral\ReferralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

/** Referal: dəvət olunanın ilk sifariş endirimi (checkout) və "Təhvil verildi"-də bonusların yazılması */
class ReferralRewardTest extends TestCase
{
    use OrderStatusFixtures;

    private Customer $referrer;
    private Customer $invitee;

    protected function setUp(): void
    {
        parent::setUp();
        // Developer bazasına toxunmuruq: yaddaşda sqlite
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('surname')->nullable(); $t->boolean('active')->default(true); $t->string('referral_code')->nullable();
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('customer_referrals', function (Blueprint $t) {
            $t->id(); $t->integer('referrer_id'); $t->integer('invitee_id')->unique(); $t->string('status')->default('registered');
            $t->unsignedBigInteger('order_id')->nullable(); $t->decimal('referrer_amount', 10, 2)->nullable(); $t->decimal('invitee_amount', 10, 2)->nullable();
            $t->boolean('referrer_rewardable')->default(true); $t->timestamp('rewarded_at')->nullable(); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('expired_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            $t->unsignedBigInteger('payment_method_id')->nullable();
            foreach (['subtotal', 'discount', 'referral_discount', 'total', 'bonus_earned'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->timestamps();
        });
        $this->orderStatusFixtures();
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'cash'], ['id' => 2, 'code' => 'installment'], ['id' => 3, 'code' => 'birbank_installment']]);

        // sifariş bonusu (təhvildə, BonusService::earnOnDelivery) referal hesabına qarışmasın
        $this->settings(['referral_enabled' => 1, 'order_bonus_percent' => 0]);
        $this->referrer = Customer::forceCreate(['name' => 'Dəvət edən']);
        $this->invitee = Customer::forceCreate(['name' => 'Dost', 'surname' => 'Əliyev']);
        CustomerReferral::create(['referrer_id' => $this->referrer->id, 'invitee_id' => $this->invitee->id]);
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string) $value]);
        }
    }

    private function order(array $attributes = []): Order
    {
        return Order::forceCreate($attributes + [
            'customer_id' => $this->invitee->id, 'order_status_id' => 11, 'payment_method_id' => 1,
            'order_no' => 'PS'.random_int(1000, 9999), 'subtotal' => 100, 'total' => 100,
        ]);
    }

    private function deliver(Order $order): void
    {
        app(OrderStatusService::class)->set($order, 'delivered', null);
    }

    private function service(): ReferralService
    {
        return app(ReferralService::class); // ayarlar hər dəfə yenidən oxunur
    }

    public function test_discount_mode_offers_first_order_discount_with_conditions(): void
    {
        $service = $this->service();
        $this->assertSame(10.0, $service->discountFor($this->invitee, 100, 0, 'cash'));
        $this->assertSame(5.0, $service->discountFor($this->invitee, 5, 0, 'cash'), 'məhsulların dəyərindən çox deyil');
        $this->assertSame(0.0, $service->discountFor($this->referrer, 100, 0, 'cash'), 'dəvətlə gəlməyib');
        $this->assertSame(0.0, $service->discountFor($this->invitee, 100, 5, 'cash'), 'promo ilə birlikdə işləmir (standart)');
        $this->assertSame(0.0, $service->discountFor($this->invitee, 100, 0, 'installment'));
        $this->assertSame(10.0, $service->discountFor($this->invitee, 100, 0, 'birbank_installment'), 'Birbank taksit hissə-hissə sayılmır');

        $this->settings(['referral_discount_with_promo' => 1, 'referral_installment_allowed' => 1, 'referral_min_order_enabled' => 1, 'referral_min_order_amount' => 60]);
        $service = $this->service();
        $this->assertSame(10.0, $service->discountFor($this->invitee, 100, 5, 'installment'));
        $this->assertSame(0.0, $service->discountFor($this->invitee, 59.99, 0, 'cash'), 'minimum məbləğ');

        // Ləğv olunmamış sifarişi varsa — artıq ilk sifariş deyil; ləğv olunubsa endirim yenə mövcuddur
        $order = $this->order();
        $this->assertNull($service->discountOffer($this->invitee));
        app(OrderStatusService::class)->set($order, 'cancelled', null);
        $this->assertNotNull($service->discountOffer($this->invitee->fresh()));

        $this->settings(['referral_invitee_mode' => 'balance']);
        $this->assertNull($this->service()->discountOffer($this->invitee), 'balans rejimində endirim yoxdur');
        $this->settings(['referral_invitee_mode' => 'discount', 'referral_enabled' => 0]);
        $this->assertNull($this->service()->discountOffer($this->invitee), 'proqram söndürülüb');
    }

    public function test_delivery_rewards_referrer_and_records_invitee_discount_once(): void
    {
        $this->settings(['referral_expiry_enabled' => 1, 'referral_referrer_expiry_days' => 90]);
        $order = $this->order(['discount' => 10, 'referral_discount' => 10, 'total' => 90]);
        $this->deliver($order);

        $this->assertEquals(17, $this->referrer->fresh()->bonus_balance);
        $this->assertEquals(0, $this->invitee->fresh()->bonus_balance, 'endirim rejimində dəvət olunan bonusu checkout-da alıb');
        $lot = DB::table('customer_bonus_transactions')->where('customer_id', $this->referrer->id)->first();
        $this->assertSame('referral', $lot->type);
        $this->assertSame('Dəvət bonusu (Dost Əliyev)', $lot->note, 'qeyddə dəvət olunanın adı');
        $this->assertNotNull($lot->expires_at);

        $referral = CustomerReferral::first();
        $this->assertSame(CustomerReferral::STATUS_REWARDED, $referral->status);
        $this->assertSame($order->id, (int) $referral->order_id);
        $this->assertEquals(17, $referral->referrer_amount);
        $this->assertEquals(10, $referral->invitee_amount);

        // İkinci sifariş təhvil verilsə — bonus təkrar yazılmır
        $this->deliver($this->order());
        $this->assertEquals(17, $this->referrer->fresh()->bonus_balance);
        $this->assertSame(1, DB::table('customer_bonus_transactions')->count());
    }

    public function test_balance_mode_credits_both(): void
    {
        $this->settings(['referral_invitee_mode' => 'balance']);
        $this->deliver($this->order());

        $this->assertEquals(17, $this->referrer->fresh()->bonus_balance);
        $this->assertEquals(10, $this->invitee->fresh()->bonus_balance);
        $this->assertNull(DB::table('customer_bonus_transactions')->value('expires_at'), 'müddət söndürülüb — müddətsiz');
    }

    public function test_non_qualifying_order_keeps_referral_pending_for_next_order(): void
    {
        $this->settings(['referral_min_order_enabled' => 1, 'referral_min_order_amount' => 60]);
        $this->deliver($this->order(['subtotal' => 40, 'total' => 40]));
        $this->deliver($this->order(['payment_method_id' => 2]));
        $this->assertSame(CustomerReferral::STATUS_REGISTERED, CustomerReferral::first()->status);
        $this->assertEquals(0, $this->referrer->fresh()->bonus_balance);

        $this->deliver($this->order(['subtotal' => 80, 'total' => 80]));
        $this->assertSame(CustomerReferral::STATUS_REWARDED, CustomerReferral::first()->status);
        $this->assertEquals(17, $this->referrer->fresh()->bonus_balance);
    }

    public function test_limit_reached_referrer_gets_nothing_and_disabled_program_pays_nothing(): void
    {
        CustomerReferral::first()->update(['referrer_rewardable' => false]);
        $this->deliver($this->order());
        $this->assertEquals(0, $this->referrer->fresh()->bonus_balance);
        $this->assertSame(CustomerReferral::STATUS_REWARDED, CustomerReferral::first()->status);

        $other = Customer::forceCreate(['name' => 'Başqa dost']);
        CustomerReferral::create(['referrer_id' => $this->referrer->id, 'invitee_id' => $other->id]);
        $this->settings(['referral_enabled' => 0]);
        $this->deliver($this->order(['customer_id' => $other->id]));
        $this->assertSame(CustomerReferral::STATUS_REGISTERED, CustomerReferral::where('invitee_id', $other->id)->value('status'));
    }
}
