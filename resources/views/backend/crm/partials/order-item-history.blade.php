{{-- Məhsulların tarixçəsi: sorğu, anbar cavabı, anbar seçimi/ləğvi — yeni yuxarıda --}}
@php
    $staff = $staff ?? collect();
    $who = fn ($id) => $id ? ($staff[$id] ?? 'Əməkdaş #'.$id) : null;
    $sources = ['phone' => 'Telefon', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'manual' => 'Digər'];
    $itemName = fn ($item) => ($item?->product?->name ?? 'Silinmiş məhsul').($item?->variant?->size?->name_az ? ' · '.$item->variant->size->name_az : '');
    $events = collect();
    foreach ($requests as $req) {
        $events->push(['at' => $req->created_at, 'item' => null, 'tone' => 'warn', 'icon' => 'send',
            'text' => $req->warehouse->name_az.' — sorğu #'.$req->id.' yaradıldı: '.$req->items->map(fn ($ri) => $itemName($ri->orderItem).' ×'.$ri->requested_quantity)->implode(', '),
            'by' => $who($req->created_by), 'note' => null]);
        foreach ($req->items as $ri) {
            foreach ($ri->offers as $offer) {
                $events->push(['at' => $offer->created_at, 'item' => $ri->order_item_id, 'tone' => $offer->available_quantity ? '' : 'danger', 'icon' => 'message',
                    'text' => $req->warehouse->name_az.' cavab verdi: '.($offer->available_quantity
                        ? $offer->available_quantity.' ədəd'.($offer->unit_cost !== null ? ' × '.number_format((float) $offer->unit_cost, 2).' AZN' : '')
                        : 'yoxdur').' · '.($sources[$offer->source] ?? $offer->source),
                    'by' => $who($offer->recorded_by), 'note' => $offer->note]);
            }
        }
    }
    foreach ($order->items as $item) {
        foreach ($item->allocations as $allocation) {
            foreach ($allocation->logs as $log) {
                $bad = in_array($log->to_status, ['cancelled', 'problem'], true);
                $stepLabel = [
                    'selected' => 'seçildi', 'notified' => '— anbara bildirildi', 'reserved' => '— anbar ayırdı',
                    'picked' => '— götürüldü', 'problem' => '— problem', 'cancelled' => 'seçimi ləğv edildi',
                    'returning' => '— qapıda imtina, anbara qaytarılır', 'returned' => '— anbara qaytarıldı',
                ][$log->to_status] ?? $log->to_status;
                if ($log->from_status === 'problem' && $log->to_status !== 'cancelled') $stepLabel = '— problem həll edildi';
                $events->push(['at' => $log->created_at, 'item' => $item->id, 'tone' => $bad ? 'danger' : '', 'icon' => $bad ? 'close' : 'check',
                    'text' => $allocation->warehouse->name_az.' '.$stepLabel.' · '.$allocation->quantity.' ədəd × '.number_format((float) $allocation->unit_cost, 2).' AZN',
                    'by' => $who($log->user_id), 'note' => $log->note]);
            }
        }
    }
    foreach ($order->itemCancellations as $c) {
        $events->push(['at' => $c->created_at, 'item' => $c->order_item_id, 'tone' => 'danger', 'icon' => 'close',
            'text' => $c->quantity.' ədəd ləğv edildi · '.$c->reasonLabel().' · '.number_format((float) $c->amount, 2).' AZN'
                .((float) $c->bonus_adjustment > 0 ? ' · bonus −'.number_format((float) $c->bonus_adjustment, 2) : '')
                .($c->customer_agreed ? ' · müştəri razıdır' : ''),
            'by' => $who($c->created_by), 'note' => $c->note]);
    }
    $events = $events->sortByDesc('at')->values();
    $items = $order->items->keyBy('id');
@endphp

@if($events->isEmpty())
    <p class="text-muted mb-0">Hələ təminat əməliyyatı yoxdur. Anbar sorğusu yaradılanda burada görünəcək.</p>
@else
    <div class="d-flex flex-wrap gap-2 mb-3" data-history-filter>
        <button type="button" class="btn btn-sm btn-primary" data-item="">Hamısı</button>
        @foreach($order->items as $item)
            <button type="button" class="btn btn-sm btn-outline-primary" data-item="{{ $item->id }}">{{ $itemName($item) }}</button>
        @endforeach
    </div>
    <ul class="od-events">
        @foreach($events as $event)
            <li data-item="{{ $event['item'] }}">
                <time>{{ $event['at']?->format('d.m.Y H:i') }}</time>
                <span class="od-events__icon {{ $event['tone'] ? 'is-'.$event['tone'] : '' }}"><i data-acorn-icon="{{ $event['icon'] }}" data-acorn-size="14"></i></span>
                <div>
                    @if($event['item'] && $items->has($event['item']))<b>{{ $itemName($items[$event['item']]) }}</b> — @endif{{ $event['text'] }}
                    @if($event['note'])<div class="od-events__note">{{ $event['note'] }}</div>@endif
                    @if($event['by'])<div class="od-events__who">{{ $event['by'] }}</div>@endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
