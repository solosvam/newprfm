<?php

namespace Tests\Feature\Procurement;

use App\Models\Order\Order;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequest;
use App\Models\Procurement\WarehouseRequestItem;
use App\Services\OrderProcessSummary;
use App\Services\ProcurementService;
use Tests\Concerns\OrderStatusFixtures;
use Tests\Concerns\ProcurementSchema;
use Tests\TestCase;

class OrderProcessSummaryTest extends TestCase
{
    use OrderStatusFixtures;
    use ProcurementSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurementSchema();
        $this->orderStatusFixtures();
    }

    private function summary(Order $order, ?string $courierBlock = null): array
    {
        $requests = WarehouseRequest::with(['warehouse', 'items.offers', 'items.orderItem'])->where('order_id', $order->id)->get();

        return app(OrderProcessSummary::class)->for($order->fresh(), $requests, collect([7 => 'Rufat']), $courierBlock);
    }

    public function test_items_are_grouped_and_ordered_by_what_the_operator_must_do_next(): void
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]); // preparing
        $selected = $order->items()->create(['quantity' => 1]);
        $waiting = $order->items()->create(['quantity' => 1]);
        $choose = $order->items()->create(['quantity' => 1]);
        $service = app(ProcurementService::class);
        $service->createRequests($order, [Warehouse::create(['name_az' => 'Anbar A', 'active' => true])->id], [$selected->id, $waiting->id, $choose->id], 7);
        $requestItems = WarehouseRequestItem::all()->keyBy('order_item_id');

        // Hələ heç bir cavab yoxdur
        $summary = $this->summary($order);
        $this->assertEquals(['choose' => 0, 'waiting' => 3, 'selected' => 0], $summary['counts']);
        $this->assertSame('info', $summary['tone']);
        $this->assertStringContainsString('3 məhsul üçün anbar cavabı gözlənilir', $summary['headline']);

        $offer = $service->recordOffer($order, $requestItems[$selected->id]->id, ['available_quantity' => 1, 'unit_cost' => '50', 'source' => 'phone'], 7);
        $service->allocate($order, $offer->id, 1, 7);
        $service->recordOffer($order, $requestItems[$choose->id]->id, ['available_quantity' => 1, 'unit_cost' => '60', 'source' => 'phone'], 7);

        $summary = $this->summary($order);

        $this->assertSame('choose', $summary['items'][$choose->id]['group']);
        $this->assertSame('waiting', $summary['items'][$waiting->id]['group']);
        $this->assertSame('selected', $summary['items'][$selected->id]['group']);
        // Sıra: cavab gələn → cavab gözləyən → seçilən (sifarişdəki sıradan asılı olmayaraq)
        $this->assertSame([$choose->id, $waiting->id, $selected->id], $summary['order']);
        $this->assertSame('warning', $summary['tone']);
        $this->assertStringContainsString('1 məhsul üçün anbar seçilməlidir', $summary['headline']);
        $this->assertStringContainsString('1 məhsul hələ cavab gözləyir', $summary['detail']);
        $this->assertSame('Rufat', $summary['last']['by']);
    }

    public function test_headline_when_everything_is_selected_and_when_no_request_was_sent(): void
    {
        $order = Order::create(['customer_id' => 1, 'order_status_id' => 1]);
        $item = $order->items()->create(['quantity' => 1]);

        $summary = $this->summary($order);
        $this->assertStringContainsString('1 məhsul üçün anbarlara sorğu göndərilməyib', $summary['headline']);

        $service = app(ProcurementService::class);
        $service->createRequests($order, [Warehouse::create(['name_az' => 'Anbar A', 'active' => true])->id], [$item->id], 7);
        $offer = $service->recordOffer($order, WarehouseRequestItem::first()->id, ['available_quantity' => 1, 'unit_cost' => '50', 'source' => 'phone'], 7);
        $service->allocate($order, $offer->id, 1, 7);

        $ready = $this->summary($order);
        $this->assertSame('success', $ready['tone']);
        $this->assertStringContainsString('kuryer təyin edə bilərsiniz', $ready['headline']);

        $blocked = $this->summary($order, 'Kuryer üçün ünvan lazımdır.');
        $this->assertSame('info', $blocked['tone']);
        $this->assertSame('Kuryer üçün ünvan lazımdır.', $blocked['detail']);
    }
}
