<?php

namespace Tests\Feature\Stock;

use App\Models\Stock\GiftCard;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GiftCardStockTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
        Schema::table('permissions', fn (Blueprint $t) => $t->string('description')->nullable());
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('surname')->nullable(), $t->timestamps()]);
        Schema::create('orders', fn (Blueprint $t) => [$t->id(), $t->string('order_no'), $t->timestamps()]);
        (require database_path('migrations/2026_11_02_120000_create_stock_and_gift_cards.php'))->up();
        $this->admin = User::forceCreate(['name' => 'Rufat']);
        Gate::before(fn () => true);
        $this->actingAs($this->admin, 'admin');
    }

    private function purchase(array $lines)
    {
        return $this->post(route('admin.stock.purchases.store'), ['purchased_at' => '2026-11-02', 'supplier' => 'Çap evi', 'lines' => $lines]);
    }

    public function test_barcode_rule(): void
    {
        $this->assertSame('1000100000001', GiftCard::barcodeFor(100, 1));
        $this->assertSame('1000200000075', GiftCard::barcodeFor(200, 75));
        $this->assertSame('1001000000050', GiftCard::barcodeFor(1000, 50));
        $this->assertTrue(GiftCard::barcodeMatches('1000500000001', 500));
        $this->assertFalse(GiftCard::barcodeMatches('1000500000001', 100));
    }

    public function test_purchase_puts_every_card_in_stock_with_history(): void
    {
        $this->purchase([
            ['denomination' => 100, 'barcode_from' => '1000100000001', 'barcode_to' => '1000100000200', 'unit_cost' => '0.5'],
            ['denomination' => 1000, 'barcode_from' => '1001000000001', 'barcode_to' => '1001000000050', 'unit_cost' => '0.5'],
        ])->assertRedirect()->assertSessionHas('success', '250 kart stoka daxil edildi.');

        $this->assertSame(200, GiftCard::where('denomination', 100)->where('status', GiftCard::IN_STOCK)->count());
        $this->assertSame(50, GiftCard::where('denomination', 1000)->count());
        $this->assertEquals(125, (float) DB::table('stock_purchases')->value('total_cost'));
        $this->assertSame(250, DB::table('gift_card_events')->where('event', 'received')->count());
        $this->get(route('admin.stock.cards', ['denomination' => 100]))->assertOk()->assertSee('1000100000001')->assertSee('200 <span', false);
        $this->get(route('admin.stock.purchases'))->assertOk()->assertSee('HK 100, HK 1000');

        // Eyni barkodlar ikinci dəfə və nominala uyğun olmayan barkod — rədd
        $this->purchase([['denomination' => 100, 'barcode_from' => '1000100000150', 'barcode_to' => '1000100000250', 'unit_cost' => '0.5']])
            ->assertSessionHasErrors('lines.0');
        $this->purchase([['denomination' => 200, 'barcode_from' => '1000100000300', 'barcode_to' => '1000100000310', 'unit_cost' => '0.5']])
            ->assertSessionHasErrors('lines.0');
        $this->assertSame(250, GiftCard::count());
    }

    public function test_sell_and_return_to_stock(): void
    {
        $this->purchase([['denomination' => 200, 'barcode_from' => '1000200000001', 'barcode_to' => '1000200000075', 'unit_cost' => '0.5']]);
        DB::table('orders')->insert(['id' => 9, 'order_no' => 'PS777']);
        $card = GiftCard::where('barcode', '1000200000003')->firstOrFail();

        // Skanerlə tam barkod — kartın səhifəsi
        $this->get(route('admin.stock.cards', ['q' => '1000200000003']))->assertRedirect(route('admin.stock.cards.show', $card));

        $this->post(route('admin.stock.cards.sell', $card), ['order_no' => 'PS999'])->assertSessionHasErrors('order_no');
        $this->post(route('admin.stock.cards.sell', $card), ['order_no' => 'PS777'])->assertSessionHas('success');
        $this->assertSame([GiftCard::SOLD, 9], [$card->fresh()->status, (int) $card->fresh()->sold_order_id]);
        $this->post(route('admin.stock.cards.sell', $card))->assertSessionHasErrors('status'); // təkrar satmaq olmaz

        $this->post(route('admin.stock.cards.return', $card))->assertSessionHasErrors('note');
        $this->post(route('admin.stock.cards.return', $card), ['note' => 'səhv'])->assertSessionHas('success');
        $this->assertSame(GiftCard::IN_STOCK, $card->fresh()->status);
        $this->get(route('admin.stock.cards.show', $card))->assertOk()->assertSee('Stoka qaytarıldı')->assertSee('PS777');
    }
}
