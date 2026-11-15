<?php

namespace Tests\Feature\GiftCard;

use App\Exceptions\GiftCardException;
use App\Models\Customer\Customer;
use App\Models\GiftCard\GiftCard;
use App\Models\Order\Order;
use App\Models\Order\OrderItemCancellation;
use App\Models\Payment\Payment;
use App\Services\GiftCard\GiftCardService;
use App\Services\OrderItemCancellationService;
use App\Services\Payment\PaymentItemsBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\TestCase;

/** Virtual hədiyyə kartı: alış qaydaları, kodun verilməsi, tətbiq, ləğvdə bərpa, ödəniş sətirləri */
class GiftCardTest extends TestCase
{
    use OrderStatusFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('order_statuses', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name_az')->nullable(); });
        Schema::create('payment_methods', function (Blueprint $t) { $t->id(); $t->string('code'); });
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('mobile')->nullable(); $t->boolean('active')->default(true);
            $t->decimal('bonus_balance', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('customer_bonus_transactions', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->string('type');
            $t->decimal('amount', 12, 2); $t->string('note')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->string('order_no')->default('PS1'); $t->unsignedBigInteger('order_status_id');
            $t->unsignedBigInteger('payment_method_id'); $t->string('payment_status')->default('pending');
            foreach (['subtotal', 'discount', 'referral_discount', 'delivery_fee', 'gift_wrap_fee', 'total', 'bonus_earned', 'gift_card_amount'] as $c) $t->decimal($c, 12, 2)->default(0);
            $t->unsignedBigInteger('gift_card_id')->nullable();
            $t->boolean('gift_wrap')->default(false);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained(); $t->integer('product_id')->nullable(); $t->integer('product_variant_id')->nullable();
            $t->decimal('unit_price', 12, 2); $t->decimal('list_price', 12, 2)->nullable(); $t->integer('quantity'); $t->decimal('total', 12, 2);
            $t->boolean('gift_wrap')->default(false); $t->decimal('gift_wrap_fee', 10, 2)->default(0); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->integer('customer_id'); $t->unsignedBigInteger('order_id')->nullable(); $t->unsignedBigInteger('virtual_gift_card_id')->nullable();
            $t->string('provider'); $t->string('provider_order_id')->nullable(); $t->decimal('amount', 12, 2); $t->string('status');
            $t->string('response_text')->nullable(); $t->timestamps();
        });
        Schema::create('virtual_gift_cards', function (Blueprint $t) {
            $t->id(); $t->string('code', 20)->nullable()->unique(); $t->decimal('amount', 10, 2); $t->string('design', 20)->default('standard');
            $t->string('message', 150)->nullable(); $t->string('sender_name', 60)->nullable(); $t->integer('customer_id');
            $t->string('status', 15)->default('pending'); $t->string('share_token', 48)->unique();
            foreach (['paid_at', 'expires_at', 'used_at', 'refunded_at', 'reminded_at'] as $c) $t->timestamp($c)->nullable();
            $t->unsignedBigInteger('used_order_id')->nullable(); $t->timestamps();
        });
        Schema::create('virtual_gift_card_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('virtual_gift_card_id'); $t->string('event', 20); $t->unsignedBigInteger('order_id')->nullable();
            $t->string('note')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamp('created_at');
        });
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->up();
        (require database_path('migrations/2026_11_15_090000_add_is_own_to_warehouses.php'))->up();
        (require database_path('migrations/2026_11_12_140000_add_opening_debt_to_warehouses.php'))->up();
        (require database_path('migrations/2026_09_29_160000_create_order_item_cancellations_table.php'))->up();
        (require database_path('migrations/2026_10_06_203155_add_fee_type_to_order_item_cancellations.php'))->up();
        (require database_path('migrations/2026_09_29_190000_add_supply_flow_to_order_item_allocations.php'))->up();
        (require database_path('migrations/2026_11_02_100000_add_courier_pays_to_order_item_allocations.php'))->up();
        (require database_path('migrations/2026_10_10_160000_make_order_item_cancellations_created_by_nullable.php'))->up();
        (require database_path('migrations/2026_09_29_150000_create_payment_items_table.php'))->up();
        Schema::create('payment_operations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('payment_id'); $t->string('type'); $t->decimal('amount', 12, 2)->nullable(); $t->string('status');
            $t->string('idempotency_key')->nullable(); $t->string('bank_action_id')->nullable(); $t->text('response_text')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_09_29_170000_create_payment_refund_items_table.php'))->up();

        $this->orderStatusFixtures();
        DB::table('payment_methods')->insert([['id' => 1, 'code' => 'card_online'], ['id' => 2, 'code' => 'cash'], ['id' => 5, 'code' => 'gift_card']]);
        DB::table('customers')->insert(['id' => 1, 'name' => 'Test', 'mobile' => '994501112233']);
        DB::table('settings')->insert(['key' => 'order_bonus_percent', 'value' => '5']);
        DB::table('products')->insert([['id' => 1, 'name' => 'Rose Oud'], ['id' => 2, 'name' => 'Gold Knight']]);
    }

    private function service(): GiftCardService
    {
        return app(GiftCardService::class);
    }

    /** Ödənilmiş, aktiv kart */
    private function activeCard(float $amount = 200): GiftCard
    {
        $card = $this->service()->purchase(Customer::find(1), ['amount' => $amount]);
        $payment = Payment::create(['customer_id' => 1, 'order_id' => null, 'virtual_gift_card_id' => $card->id, 'provider' => 'birbank', 'amount' => $amount, 'status' => Payment::PAID]);
        $this->service()->applyPayment($payment);

        return $card->fresh();
    }

    /**
     * 150×2 + 100×1, promo 10, çatdırılma 5 → 395; kartla 200, qalan 195 seçilən üsulla.
     * Onlayn ödənilibsə ödəniş (195) yaranır və sətirləri qalığa uyğun paylanır.
     */
    private function giftOrder(GiftCard $card, string $method = 'card_online'): Order
    {
        $online = $method === 'card_online';
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => $online ? 1 : 2,
            'payment_status' => $online ? 'paid' : 'cod', 'subtotal' => 400, 'discount' => 10, 'delivery_fee' => 5,
            'gift_card_id' => $card->id, 'gift_card_amount' => 200, 'total' => 195]);
        $order->items()->create(['product_id' => 1, 'unit_price' => 150, 'quantity' => 2, 'total' => 300]);
        $order->items()->create(['product_id' => 2, 'unit_price' => 100, 'quantity' => 1, 'total' => 100]);
        $this->service()->redeem($order, $card, 200);
        if ($online) {
            Payment::create(['customer_id' => 1, 'order_id' => $order->id, 'provider' => 'birbank', 'amount' => 195, 'status' => Payment::PAID]);
        }

        return $order->fresh('items');
    }

    public function test_amount_must_be_between_100_and_1500_in_steps_of_50(): void
    {
        $this->assertSame(100, $this->service()->amounts()[0]);
        $this->assertSame(1500, last($this->service()->amounts()));
        foreach ([100, 150, 1500, '250'] as $ok) {
            $this->assertTrue($this->service()->isValidAmount($ok), (string) $ok);
        }
        foreach ([50, 119, 213.5, 1550, 0, 'abc', null] as $bad) {
            $this->assertFalse($this->service()->isValidAmount($bad), var_export($bad, true));
        }

        $this->expectException(ValidationException::class);
        $this->service()->purchase(Customer::find(1), ['amount' => 119]);
    }

    public function test_message_with_banned_word_is_rejected(): void
    {
        $this->assertTrue($this->service()->containsBannedWord('Sən bir SİKdirsən'));
        $this->assertTrue($this->service()->containsBannedWord('SIKDIR'));
        $this->assertTrue($this->service()->containsBannedWord('ну ты и сука'));
        $this->assertFalse($this->service()->containsBannedWord('Ad günün mübarək, əzizim! Musiqi və ətir.'));

        try {
            $this->service()->purchase(Customer::find(1), ['amount' => 200, 'message' => 'what the fuck']);
            $this->fail('Qadağan söz qəbul olundu');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('message', $e->errors());
        }
        $this->assertSame(0, GiftCard::count());
    }

    public function test_code_is_given_only_after_payment_and_card_is_valid_for_12_months(): void
    {
        $card = $this->service()->purchase(Customer::find(1), ['amount' => 300, 'message' => '  Ad günün   mübarək ', 'sender_name' => 'Aysel']);
        $this->assertSame(GiftCard::PENDING, $card->status);
        $this->assertNull($card->code);
        $this->assertSame('Ad günün mübarək', $card->message);
        $this->assertSame(40, strlen($card->share_token));

        // Uğursuz ödəniş kodu vermir
        $failed = Payment::create(['customer_id' => 1, 'virtual_gift_card_id' => $card->id, 'provider' => 'birbank', 'amount' => 300, 'status' => Payment::FAILED]);
        $this->service()->applyPayment($failed);
        $this->assertNull($card->fresh()->code);

        $paid = Payment::create(['customer_id' => 1, 'virtual_gift_card_id' => $card->id, 'provider' => 'birbank', 'amount' => 300, 'status' => Payment::PAID]);
        $this->service()->applyPayment($paid);
        $card->refresh();
        $this->assertSame(GiftCard::ACTIVE, $card->status);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{12}$/', $card->code);
        $this->assertMatchesRegularExpression('/^\w{4}-\w{4}-\w{4}$/', $card->formattedCode());
        $this->assertTrue($card->expires_at->isSameDay(now()->addMonths(12)));
        $this->assertSame(0, DB::table('payment_items')->count()); // kartın alışı sifariş deyil

        // Təkrar nəticə kodu dəyişmir
        $code = $card->code;
        $this->service()->applyPayment($paid);
        $this->assertSame($code, $card->fresh()->code);
        $this->assertSame(1, $card->events()->where('event', 'paid')->count());

        // Maliyyə: kartın alışı "Satış" deyil — Pul axınında "Hədiyyə kartı satışı" (satış kartla mal veriləndə yaranır)
        foreach (['2026_09_29_200000_create_finance_tables.php', '2026_10_16_100000_create_finance_categories.php', '2026_11_16_130000_gift_card_sale_category.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        app(\App\Services\FinanceService::class)->recordOnlinePayment($paid);
        $movement = \App\Models\Finance\MoneyMovement::where('payment_id', $paid->id)->sole();
        $this->assertSame('gift_card_sale', $movement->category->code);
        $this->assertSame('300.00', $movement->amount);
    }

    public function test_code_is_found_regardless_of_case_dashes_and_spaces(): void
    {
        $card = $this->activeCard();
        $typed = strtolower(implode(' - ', str_split($card->code, 4)));

        $this->assertSame($card->id, $this->service()->find($typed)->id);

        $this->expectException(GiftCardException::class);
        $this->service()->find('ABCD-EFGH-JKLM');
    }

    public function test_expired_and_used_cards_cannot_be_applied(): void
    {
        $card = $this->activeCard();
        $card->update(['expires_at' => now()->subDay()]);
        try {
            $this->service()->find($card->code);
            $this->fail('Müddəti bitmiş kart qəbul olundu');
        } catch (GiftCardException $e) {
            $this->assertSame(__('gift_card_expired'), $e->getMessage());
        }
        $this->assertSame(1, $this->service()->expireDue());
        $this->assertSame(GiftCard::EXPIRED, $card->fresh()->status);

        $used = $this->activeCard(150);
        $this->giftOrder($used, 'cash');
        $this->expectExceptionMessage(__('gift_card_already_used'));
        $this->service()->find($used->code);
    }

    public function test_card_larger_than_order_burns_the_rest(): void
    {
        $card = $this->activeCard(500);
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1, 'payment_method_id' => 5, 'payment_status' => 'paid', 'subtotal' => 300, 'total' => 0]);

        $this->assertEquals(300, $this->service()->redeem($order, $card, 300));
        $card->refresh();
        $this->assertSame(GiftCard::USED, $card->status);
        $this->assertSame($order->id, (int) $card->used_order_id);
        $this->assertStringContainsString('qalan 200.00 AZN yandı', $card->events()->where('event', 'used')->value('note'));
    }

    public function test_online_remainder_is_spread_over_payment_lines(): void
    {
        $order = $this->giftOrder($this->activeCard());
        $lines = app(PaymentItemsBuilder::class)->lines($order, 195);

        // 292.50 (promo payı çıxılmış) + 97.50 + 5 = 395 → 195-ə mütənasib, qəpik qalığı sonuncu sətrə
        $this->assertSame(['144.39', '48.13', '2.48'], array_column($lines, 'amount'));
        $this->assertEquals(195, round(array_sum(array_map('floatval', array_column($lines, 'amount'))), 2));
    }

    public function test_items_of_gift_card_order_cannot_be_cancelled_separately(): void
    {
        $order = $this->giftOrder($this->activeCard(), 'cash');

        $this->expectExceptionMessage(OrderItemCancellationService::GIFT_CARD_BLOCK);
        app(OrderItemCancellationService::class)->cancel($order, $order->items[0], 1, 'not_in_stock', null, 7);
    }

    public function test_full_cancel_refunds_online_part_by_payment_lines_and_reactivates_card(): void
    {
        $card = $this->activeCard();
        $order = $this->giftOrder($card);

        $result = app(OrderItemCancellationService::class)->cancelOrder($order, 'customer_refused', null, 7);

        $order->refresh();
        $this->assertSame('cancelled', $this->statusCode($order));
        $this->assertEquals(0, $order->total);
        $rows = collect($result['cancellations']);
        // Karta (Birbank) yalnız onlayn ödənilən 195 qaytarılır, hər sətir ödənişin öz sətri qədər
        $this->assertEquals(195, round($rows->sum('amount'), 2));
        $this->assertTrue($rows->every(fn ($c) => $c->refund_status === OrderItemCancellation::REFUND_PENDING));
        $lines = DB::table('payment_items')->get();
        foreach ($rows as $row) {
            $line = $row->fee_type ? $lines->firstWhere('type', $row->fee_type) : $lines->first(fn ($l) => $l->type === 'item' && (int) $l->order_item_id === (int) $row->order_item_id);
            $this->assertEquals((float) $line->amount, (float) $row->amount);
        }

        // Kartla ödənilən 200 karta qayıdır: kart yenidən aktivdir və tətbiq oluna bilər
        $card->refresh();
        $this->assertSame(GiftCard::ACTIVE, $card->status);
        $this->assertNull($card->used_order_id);
        $this->assertSame($card->id, $this->service()->find($card->code)->id);

        // Müştərinin hesabı: Məhsullar 400 − Endirim 10 + Çatdırılma 5 − Kart 200 − Ləğv 195 = 0
        $sum = $order->fresh('items')->totalsBreakdown();
        $this->assertEquals(10, $sum['discount']);
        $this->assertEquals(200, $sum['gift_card']);
        $this->assertEquals(195, $sum['cancelled']);
        $this->assertEquals(0, $sum['total']);
    }

    public function test_full_cancel_of_cash_gift_order_needs_no_refund(): void
    {
        $card = $this->activeCard();
        $order = $this->giftOrder($card, 'cash');

        $result = app(OrderItemCancellationService::class)->cancelOrder($order, 'not_in_stock', null, 7);

        $rows = collect($result['cancellations']);
        $this->assertEquals(195, round($rows->sum('amount'), 2));
        $this->assertTrue($rows->every(fn ($c) => $c->refund_status === null));
        $this->assertEquals(0, $order->fresh()->total);
        $this->assertSame(GiftCard::ACTIVE, $card->fresh()->status);
    }

    public function test_extend_reactivates_expired_card(): void
    {
        $card = $this->activeCard();
        $card->update(['status' => GiftCard::EXPIRED, 'expires_at' => now()->subDays(3)]);

        $this->service()->extend($card, now()->addMonth(), 9);

        $card->refresh();
        $this->assertSame(GiftCard::ACTIVE, $card->status);
        $this->assertTrue($card->isUsable());
        $this->assertSame(9, (int) $card->events()->where('event', 'extended')->value('user_id'));
    }
}
