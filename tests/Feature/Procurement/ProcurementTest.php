<?php

namespace Tests\Feature\Procurement;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\OrderStatusFixtures;
use Tests\Concerns\ProcurementSchema;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use OrderStatusFixtures;
    use ProcurementSchema;

    protected function setUp(): void
    {
        parent::setUp();
        // Never migrate or refresh the developer's database. Legacy base tables are not in migrations.
        $this->procurementSchema();
        $this->orderStatusFixtures();
    }

    private function order(int $quantity = 2): Order
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $order->items()->create(['quantity' => $quantity]);

        return $order;
    }

    private function offer(Order $order, Warehouse $warehouse, int $quantity, string $price, ?int $itemId = null)
    {
        $service = app(ProcurementService::class);
        $service->createRequests($order, [$warehouse->id], [$itemId ?? $order->items()->first()->id], 7);
        $requestItem = WarehouseRequestItem::latest('id')->first();

        return $service->recordOffer($order, $requestItem->id, ['available_quantity' => $quantity, 'unit_cost' => $price, 'source' => 'phone'], 7);
    }

    public function test_split_two_units_between_warehouses_and_keep_cost_184(): void
    {
        $order = $this->order();
        $second = $order->items()->create(['quantity' => 1]);
        $a = Warehouse::create(['name_az' => 'Anbar 1']);
        $b = Warehouse::create(['name_az' => 'Anbar 2']);
        $c = Warehouse::create(['name_az' => 'Anbar 3']);
        $service = app(ProcurementService::class);
        $service->allocate($order, $this->offer($order, $c, 1, '50')->id, 1, 7);
        $service->allocate($order, $this->offer($order, $b, 2, '54')->id, 1, 7);
        $service->allocate($order, $this->offer($order, $a, 1, '80', $second->id)->id, 1, 7);
        $this->assertSame(3, OrderItemAllocation::count());
        $this->assertEquals(184, OrderItemAllocation::get()->sum(fn ($a) => $a->quantity * $a->unit_cost));
        $this->assertSame(3, DB::table('allocation_status_logs')->count());
        // Bütün miqdar seçildi → "Anbarlar təyin olundu"
        $this->assertSame('warehouses_assigned', $this->statusCode($order));
    }

    public function test_cannot_overallocate_order_quantity(): void
    {
        $order = $this->order(1);
        $service = app(ProcurementService::class);
        $a = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $b = $this->offer($order, Warehouse::create(['name_az' => 'B']), 1, '60');
        $service->allocate($order, $a->id, 1, 7);
        $this->expectException(ValidationException::class);
        $service->allocate($order, $b->id, 1, 7);
    }

    public function test_cannot_overallocate_warehouse_offer(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($order, $offer->id, 2, 7);
    }

    public function test_cannot_allocate_offer_from_another_order(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($this->order(), $offer->id, 1, 7);
    }

    public function test_cancel_preserves_history_and_releases_quantity(): void
    {
        $order = $this->order(1);
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 1, '50');
        $service = app(ProcurementService::class);
        $allocation = $service->allocate($order, $offer->id, 1, 7);
        $service->cancelAllocation($order, $allocation->id, 'Başqa anbar seçiləcək', 7);
        $service->cancelAllocation($order, $allocation->id, 'Təkrar klik', 7);
        $this->assertSame(2, $allocation->logs()->count());
        $service->allocate($order, $offer->id, 1, 7);
        $this->assertSame(2, OrderItemAllocation::count());
        $this->assertSame(1, OrderItem::first()->quantity);
    }

    public function test_updated_offer_does_not_change_selected_price_and_old_offer_cannot_be_selected(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 2, '50');
        $service = app(ProcurementService::class);
        $allocation = $service->allocate($order, $offer->id, 1, 7);
        $service->recordOffer($order, $offer->warehouse_request_item_id, ['available_quantity' => 2, 'unit_cost' => '55', 'source' => 'phone'], 7);
        $this->assertSame('50.00', $allocation->fresh()->unit_cost);
        $this->assertSame(2, $offer->requestItem->offers()->count());
        $this->expectException(ValidationException::class);
        $service->allocate($order, $offer->id, 1, 7);
    }

    public function test_batch_request_is_atomic_when_an_item_belongs_to_another_order(): void
    {
        $order = $this->order();
        $other = $this->order();
        try {
            app(ProcurementService::class)->createRequests($order, [Warehouse::create(['name_az' => 'A'])->id], [$other->items()->first()->id], 7);
            $this->fail('Invalid item accepted');
        } catch (ValidationException $e) {
            $this->assertSame(0, DB::table('warehouse_requests')->count());
        }
    }

    public function test_unavailable_answer_does_not_require_price(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 0, '0');
        $this->assertNull($offer->unit_cost);
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->allocate($order, $offer->id, 1, 7);
    }

    public function test_completed_order_cannot_be_modified(): void
    {
        $order = $this->order();
        DB::table('order_statuses')->where('id', 1)->update(['code' => 'delivered']);
        $this->expectException(ValidationException::class);
        app(ProcurementService::class)->createRequests($order, [Warehouse::create(['name_az' => 'A'])->id], [$order->items()->first()->id], 7);
    }

    public function test_schema_rollback_removes_only_new_tables(): void
    {
        (require database_path('migrations/2026_09_29_180000_create_warehouse_access_links.php'))->down();
        (require database_path('migrations/2026_09_29_150000_create_procurement_tables.php'))->down();
        $this->assertFalse(Schema::hasTable('warehouses'));
        $this->assertTrue(Schema::hasTable('orders'));
    }

    public function test_repeated_selection_request_is_not_duplicated(): void
    {
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 2, '50');
        $key = (string) Str::uuid();
        $service = app(ProcurementService::class);
        $first = $service->allocate($order, $offer->id, 1, 7, $key);
        $second = $service->allocate($order, $offer->id, 1, 7, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderItemAllocation::count());
    }

    public function test_panel_renders_existing_responses_and_allocations(): void
    {
        $user = new User(['name' => 'Operator', 'surname' => 'Test']);
        $user->id = 7;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'Anbar A']), 2, '50');
        app(ProcurementService::class)->allocate($order, $offer->id, 1, 7);
        $this->get(route('admin.procurement.show', $order))->assertOk()->assertSee('Anbar A')->assertSee('50.00');
        $this->get(route('admin.procurement.warehouses'))->assertOk()->assertSee('Anbar A');
        $this->get(route('admin.procurement.warehouses.edit', Warehouse::first()))->assertOk()->assertSee('Anbar A');
    }

    public function test_employee_without_crm_permission_cannot_access_or_write(): void
    {
        $user = new User(['name' => 'Kuryer']);
        $user->id = 9;
        Gate::before(fn () => false);
        $this->actingAs($user, 'admin');
        $this->get(route('admin.procurement.warehouses'))->assertForbidden();
        $this->post(route('admin.procurement.warehouses.store'), ['name_az' => 'Anbar', 'active' => 1])->assertForbidden();
        $this->assertSame(0, Warehouse::count());
    }

    public function test_admin_authentication_required(): void
    {
        $this->get('/admin/procurement/warehouses')->assertRedirect(route('admin.login.form'));
    }

    public function test_standalone_order_view_renders_history_payments_and_procurement(): void
    {
        $user = new \App\Models\User(['name' => 'Operator']);
        $user->id = 7;
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $this->actingAs($user, 'admin');
        $order = $this->order();
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'Anbar A']), 2, '50');
        app(ProcurementService::class)->allocate($order, $offer->id, 1, 7);
        $order->load(['items.product', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs', 'status']);
        $order->setRelation('address', null);
        $order->setRelation('paymentMethod', null);
        $order->setRelation('statusLogs', collect());
        $payment = new \App\Models\Payment\Payment(['provider' => 'birbank', 'amount' => '100.00', 'status' => 'paid']);
        $payment->setRelation('operations', collect());
        $order->setRelation('payments', collect([$payment]));
        $customer = new \App\Models\Customer\Customer;
        $customer->id = 1;
        $html = view('backend.crm.order', [
            'order' => $order, 'customer' => $customer, 'payLinkUrl' => null,
            'timelineStatuses' => \App\Models\Order\OrderStatus::all(),
            'addresses' => collect(), 'oneClickPaymentMethods' => collect(),
            'requests' => \App\Models\Procurement\WarehouseRequest::with(['warehouse', 'items.offers', 'items.orderItem.product', 'items.orderItem.variant.size'])->get(),
            'warehouses' => Warehouse::all(), 'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Ödəniş cəhdləri', $html);
        $this->assertStringContainsString('100.00 AZN', $html);
        $this->assertStringContainsString('Anbar A', $html);
        $this->assertStringContainsString('crm-order-detail.js', $html);
    }

    public function test_old_link_shows_new_requests_only_for_its_warehouse(): void
    {
        $a = Warehouse::create(['name_az' => 'Anbar A', 'active' => true]);
        $b = Warehouse::create(['name_az' => 'Anbar B', 'active' => true]);
        $portal = app(\App\Services\WarehousePortalService::class);
        $url = $portal->issue($a, 7);
        foreach ([$a, $a, $b] as $warehouse) {
            $order = $this->order();
            app(ProcurementService::class)->createRequests($order, [$warehouse->id], [$order->items()->first()->id], 7);
        }
        $response = $this->get($url)->assertOk()->assertSee('Anbar A')->assertDontSee('Anbar B');
        $response->assertSee('<span class="wp-count">2</span>', false)->assertSee('Sorğu #1')->assertSee('Sorğu #2')->assertDontSee('Sorğu #3');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $newUrl = $portal->issue($a, 7);
        $this->assertNotSame($url, $newUrl);
        $this->get($url)->assertOk();
    }

    public function test_portal_shows_gender_and_type(): void
    {
        $a = Warehouse::create(['name_az' => 'Anbar A', 'active' => true]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Chanel']);
        DB::table('types')->insert(['id' => 1, 'name_az' => 'Eau de Parfum']);
        DB::table('genders')->insert([['id' => 1, 'name_az' => 'Qadın'], ['id' => 2, 'name_az' => 'Kişi']]);
        DB::table('products')->insert(['id' => 1, 'name' => 'Coco', 'brand_id' => 1, 'type_id' => 1]);
        DB::table('product_genders')->insert(['product_id' => 1, 'gender_id' => 1]);
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $order->items()->create(['quantity' => 1, 'product_id' => 1]);
        app(ProcurementService::class)->createRequests($order, [$a->id], [$order->items()->first()->id], 7);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);

        $this->get($url)->assertOk()
            ->assertSee('<span class="wp-chip wp-chip--gender">Qadın üçün</span>', false)
            ->assertSee('<span class="wp-chip wp-chip--type">Eau de Parfum</span>', false)
            ->assertDontSee('Kişi üçün');
    }

    public function test_warehouse_answer_is_scoped_and_replay_safe(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $b = Warehouse::create(['name_az' => 'B', 'active' => true]);
        $order = $this->order();
        app(ProcurementService::class)->createRequests($order, [$a->id, $b->id], [$order->items()->first()->id], 7);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $payload = ['availability' => 'partial', 'quantity' => 1, 'unit_cost' => '50.00'];
        $this->post($url.'/items/2', $payload)->assertNotFound();
        $this->post($url.'/items/1', $payload)->assertRedirect($url);
        $this->post($url.'/items/1', $payload)->assertRedirect($url);
        $this->assertSame(1, \App\Models\Procurement\WarehouseOffer::count());
        $offer = \App\Models\Procurement\WarehouseOffer::first();
        $this->assertSame('link', $offer->source);
        $this->assertNull($offer->recorded_by);
        $this->assertNotNull($offer->warehouse_access_link_id);
        $this->assertSame('50.00', $offer->unit_cost);
        $this->get($url)->assertSee('Cavab gözləyən sorğu yoxdur.');
        $this->get($url.'?tab=answered')->assertSee('50.00')->assertSee('Sizin cavabınız');
    }

    public function test_invalid_expired_revoked_and_inactive_links_are_rejected(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $link = \App\Models\Procurement\WarehouseAccessLink::first();
        $this->assertNotSame(basename($url), $link->token_hash);
        $this->assertMatchesRegularExpression('#/w/[A-Za-z0-9]{12}$#', $url); // qısa link
        $this->get('/w/'.str_repeat('0', 64))->assertNotFound();
        // Köhnə uzun ünvan qısa ünvana yönləndirilir
        $this->get('/warehouse-portal/'.str_repeat('a', 64))->assertRedirect('/w/'.str_repeat('a', 64));
        $link->update(['expires_at' => now()->subSecond()]);
        $this->get($url)->assertNotFound();
        $link->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->get($url)->assertNotFound();
        $link->update(['revoked_at' => null]);
        $a->update(['active' => false]);
        $this->get($url)->assertNotFound();
    }

    public function test_closed_and_cancelled_items_cannot_receive_answers(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $order = $this->order();
        app(ProcurementService::class)->createRequests($order, [$a->id], [$order->items()->first()->id], 7);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $order->items()->update(['cancelled_quantity' => 2]);
        $this->post($url.'/items/1', ['availability' => 'unavailable'])->assertNotFound();
        $order->items()->update(['cancelled_quantity' => 0]);
        $order->update(['order_status_id' => DB::table('order_statuses')->where('code', 'delivered')->value('id')]);
        $this->post($url.'/items/1', ['availability' => 'unavailable'])->assertNotFound();
        $this->assertSame(0, \App\Models\Procurement\WarehouseOffer::count());
    }

    public function test_operator_answer_is_not_overwritten_by_warehouse(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $order = $this->order();
        $offer = $this->offer($order, $a, 2, '60');
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $this->post($url.'/items/'.$offer->warehouse_request_item_id, ['availability' => 'unavailable'])->assertRedirect();
        $this->assertSame(1, \App\Models\Procurement\WarehouseOffer::count());
        $this->assertSame('60.00', $offer->fresh()->unit_cost);
        $this->get($url.'?tab=answered')->assertSee('Operator qeydə alıb');
    }

    public function test_partial_quantity_and_price_are_validated(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $order = $this->order();
        app(ProcurementService::class)->createRequests($order, [$a->id], [$order->items()->first()->id], 7);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $this->post($url.'/items/1', ['availability' => 'partial', 'quantity' => 3, 'unit_cost' => '50'])->assertSessionHasErrors('quantity');
        $this->post($url.'/items/1', ['availability' => 'available'])->assertSessionHasErrors('unit_cost');
        $this->assertSame(0, \App\Models\Procurement\WarehouseOffer::count());
    }

    public function test_warehouse_can_correct_its_answer_until_operator_selects(): void
    {
        $a = Warehouse::create(['name_az' => 'A', 'active' => true]);
        $order = $this->order();
        app(ProcurementService::class)->createRequests($order, [$a->id], [$order->items()->first()->id], 7);
        $url = app(\App\Services\WarehousePortalService::class)->issue($a, 7);
        $this->post($url.'/items/1', ['availability' => 'available', 'unit_cost' => '500.00']);
        $first = \App\Models\Procurement\WarehouseOffer::first();
        $this->get($url.'?tab=answered')->assertSee('Düzəliş et');

        // Səhv qiymət düzəldilir: köhnə cavab qalır, yenisi əlavə olunur
        $this->post($url.'/items/1', ['availability' => 'available', 'unit_cost' => '50.00', 'replaces' => $first->id])
            ->assertRedirect($url.'?tab=answered');
        $this->assertSame(2, \App\Models\Procurement\WarehouseOffer::count());
        $latest = \App\Models\Procurement\WarehouseOffer::latest('id')->first();
        $this->assertSame('50.00', $latest->unit_cost);

        // Eyni düzəlişin təkrar göndərilməsi heç nə dəyişmir
        $this->post($url.'/items/1', ['availability' => 'available', 'unit_cost' => '40.00', 'replaces' => $first->id]);
        $this->assertSame(2, \App\Models\Procurement\WarehouseOffer::count());

        // Köhnə cavabla seçim olmur, son cavabla olur; seçimdən sonra düzəliş bağlanır
        try {
            app(ProcurementService::class)->allocate($order, $first->id, 1, 7);
            $this->fail('Köhnə cavab seçilməməli idi');
        } catch (ValidationException) {
        }
        app(ProcurementService::class)->allocate($order, $latest->id, 1, 7);
        $this->get($url.'?tab=answered')->assertDontSee('Düzəliş et')->assertSee('Operator bu cavabdan seçim edib');
        $this->post($url.'/items/1', ['availability' => 'unavailable', 'replaces' => $latest->id])->assertSessionHasErrors('availability');
        $this->assertSame(2, \App\Models\Procurement\WarehouseOffer::count());
    }

    /** 2 ədəd tələb, anbar A-dan 2 ədəd təklif (50 AZN) */
    private function allocated(int $take = 1): array
    {
        $order = $this->order(2);
        $offer = $this->offer($order, Warehouse::create(['name_az' => 'A']), 2, '50');
        $allocation = app(ProcurementService::class)->allocate($order, $offer->id, $take, 7);

        return [$order, $allocation, $offer];
    }

    public function test_supply_part_moves_forward_and_item_status_is_derived(): void
    {
        [$order, $allocation, $offer] = $this->allocated(1);
        $service = app(ProcurementService::class);
        $item = fn () => $order->items()->with('allocations')->first();
        $this->assertSame('partly_allocated', $item()->supplyStatus());

        $service->allocate($order, $offer->id, 1, 7);
        $this->assertSame('allocated', $item()->supplyStatus());

        foreach (['notified', 'reserved', 'picked'] as $to) {
            $service->transition($order, $allocation->id, $to, [], 7);
        }
        $service->transition($order, $allocation->id, 'picked', [], 7); // təkrar klik — heç nə
        $this->assertSame('picked', $allocation->fresh()->status);
        $this->assertSame(4, $allocation->logs()->count());
        $this->assertSame('partly_picked', $item()->supplyStatus());
    }

    public function test_steps_can_be_skipped_but_not_reversed(): void
    {
        [$order, $allocation] = $this->allocated();
        $service = app(ProcurementService::class);
        $service->transition($order, $allocation->id, 'reserved', [], 7); // telefonla dərhal "ayırdı"
        $this->assertSame('reserved', $allocation->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->transition($order, $allocation->id, 'notified', [], 7);
    }

    public function test_price_problem_is_resolved_with_new_cost_and_returns_to_previous_step(): void
    {
        [$order, $allocation] = $this->allocated();
        $service = app(ProcurementService::class);
        $service->transition($order, $allocation->id, 'notified', [], 7);
        $service->transition($order, $allocation->id, 'problem', ['problem_type' => 'price_changed', 'note' => '55 istəyir'], 7);
        $this->assertSame('problem', $order->items()->with('allocations')->first()->supplyStatus());

        try {
            $service->transition($order, $allocation->id, 'resume', [], 7); // yeni qiymət yoxdur
            $this->fail('Yeni qiymət tələb olunmalı idi');
        } catch (ValidationException) {
        }
        $service->transition($order, $allocation->id, 'resume', ['unit_cost' => '55'], 7);

        $allocation->refresh();
        $this->assertSame('notified', $allocation->status);
        $this->assertNull($allocation->problem_type);
        $this->assertEquals(55, $allocation->unit_cost);
        $this->assertStringContainsString('50.00 → 55.00', $allocation->logs()->reorder('id', 'desc')->value('note'));
    }

    public function test_picked_part_cannot_be_cancelled_but_reserved_can(): void
    {
        [$order, $allocation, $offer] = $this->allocated();
        $service = app(ProcurementService::class);
        $second = $service->allocate($order, $offer->id, 1, 7);
        $service->transition($order, $second->id, 'reserved', [], 7);
        $service->cancelAllocation($order, $second->id, 'Anbar fikrini dəyişdi', 7);
        $this->assertSame('cancelled', $second->fresh()->status);

        $service->transition($order, $allocation->id, 'picked', [], 7);
        $this->expectException(ValidationException::class);
        $service->cancelAllocation($order, $allocation->id, 'Səhv', 7);
    }

    public function test_supply_view_renders_every_status(): void
    {
        [$order, $allocation, $offer] = $this->allocated();
        $service = app(ProcurementService::class);
        $second = $service->allocate($order, $offer->id, 1, 7);
        $service->transition($order, $allocation->id, 'picked', [], 7);
        $service->transition($order, $second->id, 'problem', ['problem_type' => 'not_found'], 7);
        $order->load(['items.product', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs', 'status']);
        $requests = \App\Models\Procurement\WarehouseRequest::where('order_id', $order->id)
            ->with(['warehouse', 'items.offers', 'items.orderItem.product', 'items.orderItem.variant.size'])->get();
        $html = view('backend.procurement.order-content', ['order' => $order, 'requests' => $requests, 'warehouses' => Warehouse::all()])->render();
        $this->assertStringContainsString('Götürülüb', $html);
        $this->assertStringContainsString('Anbarda tapılmadı', $html);
        $this->assertStringContainsString('Həll edildi, davam et', $html);
        $this->assertSame('problem', $order->items->first()->supplyStatus());
    }

    public function test_order_status_follows_procurement(): void
    {
        $order = $this->order(2);
        $item = $order->items()->first();

        // "İcraya götür"dən əvvəl sorğu olmaz
        $order->update(['order_status_id' => DB::table('order_statuses')->where('code', 'new')->value('id')]);
        try {
            app(ProcurementService::class)->createRequests($order->fresh(), [Warehouse::create(['name_az' => 'X'])->id], [$item->id], 7);
            $this->fail('Sorğu bloklanmalı idi');
        } catch (ValidationException) {
        }
        $order->update(['order_status_id' => 1]); // preparing

        $offer = $this->offer($order->fresh(), Warehouse::create(['name_az' => 'A']), 2, '50');
        $this->assertSame('warehouse_requested', $this->statusCode($order));

        $service = app(ProcurementService::class);
        $first = $service->allocate($order, $offer->id, 1, 7);
        $this->assertSame('warehouse_requested', $this->statusCode($order)); // 1/2 — hələ yox
        $service->allocate($order, $offer->id, 1, 7);
        $this->assertSame('warehouses_assigned', $this->statusCode($order));

        $service->cancelAllocation($order, $first->id, 'Anbar imtina etdi', 7);
        $this->assertSame('warehouse_requested', $this->statusCode($order)); // çatışmazlıq → geri

        $this->assertNotNull(app(\App\Services\OrderStatusService::class)->courierBlock($order->fresh()));
        $this->assertGreaterThanOrEqual(3, DB::table('order_status_logs')->where('order_id', $order->id)->count());
    }

    public function test_customer_sees_internal_steps_as_preparing_without_operator_notes(): void
    {
        $order = $this->order();
        $statuses = app(\App\Services\OrderStatusService::class);
        $order->update(['order_status_id' => DB::table('order_statuses')->where('code', 'new')->value('id')]);
        $order = $order->fresh();
        DB::table('order_statuses')->where('code', 'preparing')->update(['name_az' => 'Hazırlanır']);

        $this->assertNull($statuses->startBlock($order)); // ödəniş üsulu yoxdur — nağd kimi
        $statuses->start($order, 7);
        $statuses->set($order->fresh(), 'warehouse_requested', 7);
        $statuses->set($order->fresh(), 'courier_assigned', 7, 'Kuryer: Fərid');

        $order = \App\Models\Order\Order::with('status', 'statusLogs.status')->find($order->id);
        $this->assertSame('preparing', $order->status->forCustomer()->code);
        $timeline = $order->customerTimeline();
        $this->assertCount(1, $timeline); // hazırlanır + sorğu + kuryer → bir "Hazırlanır"
        $this->assertSame('preparing', $timeline[0]['status']->code);
        $this->assertNull($timeline[0]['note']); // "Kuryer: Fərid" müştəriyə görünmür
    }
}
