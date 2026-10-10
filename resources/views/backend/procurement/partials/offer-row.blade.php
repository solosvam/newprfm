{{-- Bir anbarın bir məhsula cavabı (cədvəl sətri). order-content.blade.php-dən çağırılır: $row, $item, $rows, $cheapest, $missing. --}}
@php
    $offer = $row['offer'];
    $canTake = $offer ? min($offer->available_quantity - $row['taken'], $missing) : 0;
    $offerUrl = route('admin.procurement.offers.store', [$order, $row['ri']]);
    $offerTitle = $itemName($item).($itemSize($item) ? ' · '.$itemSize($item) : '').' — '.$row['req']->warehouse->name_az;
@endphp
<tr>
    <td>{{ $row['req']->warehouse->name_az }}<span class="proc-sub">Sorğu #{{ $row['req']->id }} · {{ $row['req']->created_at->format('d.m H:i') }}</span></td>
    <td>
        @if(!$offer)<span class="badge bg-outline-muted">Cavab gözlənilir</span>
        @elseif(!$offer->available_quantity)<span class="badge bg-outline-danger">Yoxdur</span>
        @elseif($offer->available_quantity >= $row['ri']->requested_quantity)<span class="badge bg-outline-success">Tam var · {{ $offer->available_quantity }} ədəd</span>
        @else<span class="badge bg-outline-warning">Qismən · {{ $offer->available_quantity }} ədəd</span>@endif
        @if($row['answers'] > 1)<span class="proc-sub">{{ $row['answers'] }} cavab — sonuncu göstərilir</span>@endif
    </td>
    <td class="text-end text-nowrap">
        @if($offer?->unit_cost !== null && $offer->available_quantity)
            {{ number_format((float) $offer->unit_cost, 2) }} AZN
            @if($rows->count() > 1 && (float) $offer->unit_cost === $cheapest)<span class="badge bg-success proc-best">ən ucuz</span>@endif
        @else <span class="text-muted">—</span> @endif
    </td>
    <td>@if($offer){{ $sources[$offer->source] ?? $offer->source }}<span class="proc-sub">{{ $offer->created_at->format('d.m H:i') }}@if($who($offer->recorded_by)) · {{ $who($offer->recorded_by) }}@endif</span>@else<span class="text-muted">—</span>@endif</td>
    <td class="text-end">
        <div class="proc-actions">
            @if($row['taken'])<span class="text-success text-nowrap">{{ $row['taken'] }} seçilib</span>@endif
            @if($editable && $canTake > 0)
                <form method="POST" action="{{ route('admin.procurement.allocations.store', $order) }}" class="proc-pick">
                    @csrf
                    <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    {{-- Say yalnız birdən çox götürmək mümkün olanda soruşulur --}}
                    @if($canTake > 1)
                        <input type="number" name="quantity" min="1" max="{{ $canTake }}" value="{{ $canTake }}" class="form-control form-control-sm" aria-label="Seçilən miqdar" required>
                    @else
                        <input type="hidden" name="quantity" value="1">
                    @endif
                    <button class="btn btn-sm btn-primary">Seç</button>
                </form>
            @endif
            @if($editable)
                <button type="button" class="btn btn-sm {{ $offer ? 'btn-link px-1' : 'btn-outline-primary' }}" data-bs-toggle="modal" data-bs-target="#procOfferModal"
                        data-action="{{ $offerUrl }}" data-title="{{ $offerTitle }}" data-requested="{{ $row['ri']->requested_quantity }}">
                    {{ $offer ? 'Cavabı dəyiş' : 'Cavab daxil et' }}
                </button>
            @endif
        </div>
    </td>
</tr>
