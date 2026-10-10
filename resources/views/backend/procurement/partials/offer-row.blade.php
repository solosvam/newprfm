{{-- Bir anbarın bir məhsula cavabı (cədvəl sətri). order-content.blade.php-dən çağırılır: $row, $item, $rows, $cheapest, $cheapestUnique, $missing. --}}
@php
    $offer = $row['offer'];
    $canTake = $offer ? min($offer->available_quantity - $row['taken'], $missing) : 0;
    $offerUrl = route('admin.procurement.offers.store', [$order, $row['ri']]);
    $offerTitle = $itemName($item).($itemSize($item) ? ' · '.$itemSize($item) : '').' — '.$row['req']->warehouse->name_az;
    $requested = (int) $row['ri']->requested_quantity;
    // Cavabın nişanı: 1 ədəd soruşulubsa say yazılmır ("Var"), çox soruşulubsa neçə ədəd olduğu da göstərilir
    $answerBadge = fn ($o) => !$o->available_quantity ? ['bg-outline-danger', 'Yoxdur']
        : ($o->available_quantity >= $requested
            ? ['bg-outline-success', $requested > 1 ? 'Tam var · '.$o->available_quantity.' ədəd' : 'Var']
            : ['bg-outline-warning', 'Qismən · '.$o->available_quantity.' ədəd']);
    $history = $row['answers'] > 1 ? $row['ri']->offers->sortByDesc('id')->values() : collect();
@endphp
<tr>
    <td>{{ $row['req']->warehouse->name_az }}
        @if($row['taken'])<span class="badge bg-primary ms-1">{{ $need > 1 ? $row['taken'].' ədəd seçilib' : 'Seçilib' }}</span>@endif
        <span class="proc-sub">Sorğu #{{ $row['req']->id }} · {{ $row['req']->created_at->format('d.m H:i') }}</span></td>
    <td>
        @if(!$offer)<span class="badge bg-outline-muted">Cavab gözlənilir</span>
        @else<span class="badge {{ $answerBadge($offer)[0] }}">{{ $answerBadge($offer)[1] }}</span>@endif
        @if($history->isNotEmpty())
            <a class="proc-sub text-decoration-underline" data-bs-toggle="collapse" href="#procAnswers{{ $row['ri']->id }}" role="button"
               aria-expanded="false" aria-controls="procAnswers{{ $row['ri']->id }}">{{ $row['answers'] }} cavab — sonuncu göstərilir</a>
        @endif
    </td>
    <td class="text-end text-nowrap">
        @if($offer?->unit_cost !== null && $offer->available_quantity)
            {{ number_format((float) $offer->unit_cost, 2) }} AZN
            @if($rows->count() > 1 && $cheapestUnique && (float) $offer->unit_cost === $cheapest)<span class="badge bg-success proc-best">ən ucuz</span>@endif
        @else <span class="text-muted">—</span> @endif
    </td>
    <td>@if($offer){{ $sources[$offer->source] ?? $offer->source }}<span class="proc-sub">{{ $offer->created_at->format('d.m H:i') }}@if($who($offer->recorded_by)) · {{ $who($offer->recorded_by) }}@endif</span>@else<span class="text-muted">—</span>@endif</td>
    <td class="text-end">
        <div class="proc-actions">
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
@if($history->isNotEmpty())
    {{-- Anbarın bu məhsula verdiyi bütün cavablar (yenidən köhnəyə) — "N cavab" keçidi açır --}}
    <tr id="procAnswers{{ $row['ri']->id }}" class="collapse">
        <td colspan="5" class="bg-light">
            <div class="text-muted text-small text-uppercase mb-1">{{ $row['req']->warehouse->name_az }} — cavabların tarixçəsi</div>
            @foreach($history as $old)
                <div class="d-flex flex-wrap align-items-center gap-2 py-1 {{ $loop->first ? '' : 'text-muted' }}">
                    <span class="text-nowrap">{{ $old->created_at->format('d.m H:i') }}</span>
                    <span class="badge {{ $answerBadge($old)[0] }}">{{ $answerBadge($old)[1] }}</span>
                    @if($old->available_quantity && $old->unit_cost !== null)<span class="text-nowrap">{{ number_format((float) $old->unit_cost, 2) }} AZN</span>@endif
                    <span>{{ $sources[$old->source] ?? $old->source }}@if($who($old->recorded_by)) · {{ $who($old->recorded_by) }}@endif</span>
                    @if($old->note)<span>— {{ $old->note }}</span>@endif
                    @if($loop->first)<span class="text-success">qüvvədə olan</span>@endif
                </div>
            @endforeach
        </td>
    </tr>
@endif
