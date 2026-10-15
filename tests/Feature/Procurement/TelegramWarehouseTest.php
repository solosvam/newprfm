<?php

namespace Tests\Feature\Procurement;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseOffer;
use App\Models\SmsLog;
use App\Models\TelegramMessage;
use App\Services\ProcurementService;
use App\Services\Telegram\TelegramApi;
use App\Services\WarehouseNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\OrderStatusFixtures;
use Tests\Concerns\ProcurementSchema;
use Tests\TestCase;

/** Sorğu tipi Telegram olan anbar: düyməli mesaj, "Var" → qiymət, "Qismən" → say → qiymət, "Yoxdur", "Rezerv etdim" */
class TelegramWarehouseTest extends TestCase
{
    use OrderStatusFixtures;
    use ProcurementSchema;

    private const CHAT = '-1001234567890';

    private int $nextMessageId = 500;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurementSchema();
        $this->orderStatusFixtures();
        Schema::create('product_images', fn ($t) => [$t->id(), $t->integer('product_id'), $t->string('image'), $t->integer('sort_order')->default(0)]);
        config(['services.telegram.bot_token' => '123:ABC', 'app.url' => 'https://last.parfumshop.az']);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Lanvin']);
        DB::table('types')->insert(['id' => 1, 'name_az' => 'Eau De Parfum']);
        DB::table('genders')->insert(['id' => 2, 'name_az' => 'Qadın üçün']);
        DB::table('sizes')->insert(['id' => 1, 'name_az' => '30 ml']);
        DB::table('products')->insert(['id' => 1, 'name' => 'Marry Me', 'brand_id' => 1, 'type_id' => 1]);
        DB::table('product_genders')->insert(['product_id' => 1, 'gender_id' => 2]);
        DB::table('product_variants')->insert(['id' => 1, 'size_id' => 1]);
        DB::table('product_images')->insert(['product_id' => 1, 'image' => 'marry-me.webp']);
        Http::fake(['api.telegram.org/*' => fn () => Http::response(['ok' => true, 'result' => ['message_id' => $this->nextMessageId++]])]);
    }

    private function telegramWarehouse(): Warehouse
    {
        // Köhnə sistemdəki kimi qrup id-si "-" işarəsiz saxlanır — göndərəndə əlavə olunur
        return Warehouse::create(['name_az' => 'Aksin', 'active' => true, 'telegram_id' => ltrim(self::CHAT, '-'), 'request_channel' => 'telegram']);
    }

    private function order(int $quantity): Order
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $order->items()->create(['quantity' => $quantity, 'product_id' => 1, 'product_variant_id' => 1]);

        return $order;
    }

    private function sentTo(string $method): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/'.$method))->map(fn (Request $r) => $r->data())->values()->all();
    }

    private function webhook(array $update)
    {
        return $this->postJson(route('telegram.webhook'), $update, ['X-Telegram-Bot-Api-Secret-Token' => app(TelegramApi::class)->webhookSecret()]);
    }

    private function press(TelegramMessage $message, string $action, string $chat = self::CHAT)
    {
        return $this->webhook(['callback_query' => ['id' => 'cb', 'data' => $message->id.':'.$action, 'from' => ['first_name' => 'Kamal'],
            'message' => ['message_id' => $message->message_id, 'chat' => ['id' => (int) $chat]]]]);
    }

    private function reply(string $text)
    {
        $prompt = TelegramMessage::where('kind', TelegramMessage::PROMPT)->latest('id')->first();

        return $this->webhook(['message' => ['message_id' => 999, 'text' => $text, 'from' => ['first_name' => 'Kamal'], 'chat' => ['id' => (int) self::CHAT],
            'reply_to_message' => ['message_id' => $prompt->message_id]]]);
    }

    public function test_request_goes_to_the_group_with_photo_details_and_buttons_and_price_is_answered_by_reply(): void
    {
        $order = $this->order(1);
        $request = app(ProcurementService::class)->createRequests($order, [$this->telegramWarehouse()->id], [$order->items()->first()->id], 7)->first();

        $log = app(WarehouseNotifier::class)->notifyRequest($request, 7);

        $this->assertSame(['telegram', SmsLog::SENT], [$log->channel, $log->status]);
        $photo = $this->sentTo('sendPhoto')[0];
        $this->assertSame([self::CHAT, 'https://last.parfumshop.az/frontend/uploads/products/marry-me.webp'], [$photo['chat_id'], $photo['photo']]);
        $this->assertStringContainsString('Sizdə <b>Lanvin Marry Me</b> ətirinin <b>30 ml</b> ölçüsü <b>Eau De Parfum</b> olan <b>1 ədəd</b> var?', $photo['caption']);
        $this->assertStringContainsString('Cins: Qadın üçün', $photo['caption']);
        $this->assertSame(['✅ Var', '❌ Yoxdur'], array_column($photo['reply_markup']['inline_keyboard'][0], 'text'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'lsim')); // SMS getmir

        // "Var" → bot qiyməti soruşur; "95,5" → anbarın cavabı yazılır
        $message = TelegramMessage::where('kind', TelegramMessage::REQUEST_ITEM)->sole();
        $this->press($message, 'all')->assertOk();
        $this->assertTrue($this->sentTo('sendMessage')[0]['reply_markup']['force_reply']);
        $this->reply('95,5')->assertOk();

        $offer = WarehouseOffer::sole();
        $this->assertSame([1, '95.50', 'telegram', 0, 'Telegram: Kamal'], [(int) $offer->available_quantity, (string) $offer->unit_cost, $offer->source, (int) $offer->recorded_by, $offer->note]);
        $this->assertStringContainsString('✅ <b>Var</b> — 1 ədəd × 95.50 AZN qeyd olundu (Kamal)', $this->sentTo('editMessageCaption')[0]['caption']);
    }

    public function test_partial_quantity_wrong_input_none_and_foreign_chat(): void
    {
        $order = $this->order(3);
        $request = app(ProcurementService::class)->createRequests($order, [$this->telegramWarehouse()->id], [$order->items()->first()->id], 7)->first();
        app(WarehouseNotifier::class)->notifyRequest($request, 7);
        $message = TelegramMessage::where('kind', TelegramMessage::REQUEST_ITEM)->sole();
        $this->assertSame(['✅ Hamısı var (3)', 'Qismən'], array_column($this->sentTo('sendPhoto')[0]['reply_markup']['inline_keyboard'][0], 'text'));

        // Başqa qrupdan basılan düymə qəbul olunmur
        $this->press($message, 'none', '-100999')->assertOk();
        $this->assertSame(0, WarehouseOffer::count());

        // Qismən → say (3 olmaz) → say 2 → qiymət ("abc" olmaz) → 90
        $this->press($message, 'part');
        $this->reply('3');
        $this->assertSame(TelegramMessage::AWAIT_QUANTITY, $message->fresh()->state);
        $this->reply('2');
        $this->reply('abc');
        $this->assertSame(TelegramMessage::AWAIT_PRICE, $message->fresh()->state);
        $this->reply('90');
        $this->assertSame([2, '90.00'], [(int) WarehouseOffer::latest('id')->first()->available_quantity, (string) WarehouseOffer::latest('id')->first()->unit_cost]);

        // Cavabı dəyiş → Yoxdur
        $this->press($message, 'change');
        $this->press($message, 'none');
        $this->assertSame(0, (int) WarehouseOffer::latest('id')->first()->available_quantity);
        $this->assertNull($message->fresh()->state);
    }

    public function test_selection_message_has_reserve_button_that_marks_the_allocation_reserved(): void
    {
        $order = $this->order(1);
        $service = app(ProcurementService::class);
        $request = $service->createRequests($order, [$this->telegramWarehouse()->id], [$order->items()->first()->id], 7)->first();
        $offer = $service->recordOffer($order, $request->items()->first()->id, ['available_quantity' => 1, 'unit_cost' => '80', 'source' => 'phone'], 7);
        $allocation = $service->allocate($order, $offer->id, 1, 7);

        $log = app(WarehouseNotifier::class)->notifySelected($order, $allocation, 7);

        $this->assertTrue($log->isSent());
        $this->assertSame(OrderItemAllocation::NOTIFIED, $allocation->fresh()->status);
        $this->assertSame('✅ Rezerv etdim', $this->sentTo('sendPhoto')[0]['reply_markup']['inline_keyboard'][0][0]['text']);

        $this->press(TelegramMessage::where('kind', TelegramMessage::ALLOCATION)->sole(), 'reserve')->assertOk();
        $this->assertSame(OrderItemAllocation::RESERVED, $allocation->fresh()->status);
    }

    public function test_group_id_gets_the_minus_sign_only_when_missing(): void
    {
        $this->assertSame('-482788956', (new Warehouse(['telegram_id' => '482788956']))->telegramChatId());
        $this->assertSame('-1001234567890', (new Warehouse(['telegram_id' => '-1001234567890']))->telegramChatId());
        $this->assertNull((new Warehouse(['telegram_id' => null]))->telegramChatId());
    }

    public function test_id_command_replies_with_the_group_id(): void
    {
        $this->webhook(['message' => ['message_id' => 1, 'text' => '/id@ParfumshopBot', 'chat' => ['id' => -100555, 'title' => 'Aksin — Parfumshop']]])->assertOk();

        $this->assertSame('Bu çatın ID-si: <code>-100555</code> (Aksin — Parfumshop)', $this->sentTo('sendMessage')[0]['text']);
    }

    public function test_webhook_rejects_requests_without_the_secret(): void
    {
        $this->postJson(route('telegram.webhook'), ['callback_query' => []])->assertForbidden();
        $this->postJson(route('telegram.webhook'), [], ['X-Telegram-Bot-Api-Secret-Token' => 'yanlış'])->assertForbidden();
    }
}
