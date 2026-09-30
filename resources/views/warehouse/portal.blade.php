{{-- Anbar portalı (/w/{token}): anbar açıq sorğularına cavab verir. Login yoxdur — link = giriş. --}}
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#2b1f4a">
    <title>{{ $access->warehouse->name_az }} — Parfumshop sorğuları</title>
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/warehouse-portal.css') }}">
</head>
<body>
<header class="wp-top">
    <div class="wp-wrap">
        <span class="wp-logo" role="img" aria-label="Parfumshop"></span>
        <div class="wp-top__meta">Anbar sorğuları</div>
    </div>
</header>

<main class="wp-wrap">
    <section class="wp-hero">
        <h1>{{ $access->warehouse->name_az }}</h1>
        @if($tab === 'selected')
            <p>Bu məhsullar sizdən seçilib. Rezerv edib "Rezerv etdim" basın — kuryer gəlib götürəcək.</p>
        @else
            <p>Hər məhsul üçün mövcudluğu seçin və bir ədədin qiymətini yazın. Cavab vermək məhsulu rezervasiya etmir.</p>
        @endif
    </section>

    <nav class="wp-tabs" aria-label="Sorğular">
        <a href="{{ route('warehouse.portal', $token) }}" @if($tab === 'pending') aria-current="page" @endif>
            Sorğular @if($pendingCount)<span class="wp-count">{{ $pendingCount }}</span>@endif
        </a>
        <a href="{{ route('warehouse.portal', ['token' => $token, 'tab' => 'selected']) }}" @if($tab === 'selected') aria-current="page" @endif>
            Seçilənlər @if($selectedCount)<span class="wp-count">{{ $selectedCount }}</span>@endif
        </a>
        <a href="{{ route('warehouse.portal', ['token' => $token, 'tab' => 'answered']) }}" @if($answered) aria-current="page" @endif>Cavablar</a>
    </nav>

    @if(session('success'))<div class="wp-notice" role="status">✓ {{ session('success') }}</div>@endif
    @if($errors->any())<div class="wp-notice wp-notice--error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    @if($tab === 'selected')
        @forelse($items as $allocation)
            @include('warehouse.partials.selection', ['allocation' => $allocation])
        @empty
            <div class="wp-empty"><div class="wp-empty__icon">✓</div>Sizdən seçilən məhsul yoxdur.</div>
        @endforelse
    @else
    @forelse($items as $item)
        @php
            $quantity = min($item->requested_quantity, $item->orderItem->activeQuantity());
            $product = $item->orderItem->product;
            $size = $item->orderItem->variant?->size?->name_az;
        @endphp
        <article class="wp-card" id="item-{{ $item->id }}">
            <header class="wp-card__head">
                <div class="wp-card__brand">{{ $product?->brand?->name ?? 'Məhsul' }}</div>
                <h2 class="wp-card__title">{{ $product?->name ?? 'Məhsul məlumatı yoxdur' }}</h2>
                <div class="wp-chips">
                    @if($size)<span class="wp-chip">{{ $size }}</span>@endif
                    <span class="wp-chip wp-chip--need">Lazımdır: {{ $quantity }} ədəd</span>
                </div>
                <div class="wp-card__meta">Sorğu #{{ $item->warehouse_request_id }} · {{ $item->created_at?->format('d.m.Y H:i') }}</div>
            </header>

            @if($answered)
                @php
                    $offer = $item->offers->sortByDesc('id')->first();
                    $editable = $portal->canEdit($item);
                    $editOpen = (string) old('form_item') === (string) $item->id;
                @endphp
                <div class="wp-answer {{ $offer->available_quantity ? ($offer->available_quantity >= $quantity ? 'is-yes' : 'is-part') : 'is-no' }}">
                    <strong>{{ $offer->available_quantity ? ($offer->available_quantity >= $quantity ? 'Var' : 'Qismən var').' · '.$offer->available_quantity.' ədəd' : 'Yoxdur' }}</strong>
                    @if($offer->unit_cost !== null)<span>{{ number_format((float) $offer->unit_cost, 2) }} AZN / ədəd</span>@endif
                </div>
                @if($offer->note)<div class="wp-card__note">{{ $offer->note }}</div>@endif
                <div class="wp-card__meta">
                    {{ $offer->created_at?->format('d.m.Y H:i') }} · {{ $offer->source === 'link' ? 'Sizin cavabınız' : 'Operator qeydə alıb' }}
                    @if($item->offers->count() > 1) · {{ $item->offers->count() - 1 }} dəfə düzəldilib @endif
                </div>
                @if($editable)
                    <details class="wp-edit" @if($editOpen) open @endif>
                        <summary><span class="wp-edit__open">Düzəliş et</span><span class="wp-edit__close">Bağla</span></summary>
                        @include('warehouse.partials.answer-form', ['offer' => $offer])
                    </details>
                @elseif($offer->source === 'link')
                    <div class="wp-lock">🔒 Operator bu cavabdan seçim edib — dəyişiklik üçün bizimlə əlaqə saxlayın.</div>
                @endif
            @else
                @include('warehouse.partials.answer-form', ['offer' => null])
            @endif
        </article>
    @empty
        <div class="wp-empty">
            <div class="wp-empty__icon">✓</div>
            {{ $answered ? 'Hələ cavablandırılmış açıq sorğu yoxdur.' : 'Cavab gözləyən sorğu yoxdur.' }}
        </div>
    @endforelse
    @endif

    @if($items->hasPages())
        <nav class="wp-paging" aria-label="Səhifələr">
            @if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">← Əvvəlki</a>@else<span></span>@endif
            <span>{{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
            @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">Sonrakı →</a>@else<span></span>@endif
        </nav>
    @endif

    <p class="wp-foot">Yeni sorğuları görmək üçün səhifəni yeniləyin.</p>
</main>
<script src="{{ asset_v('frontend/js/warehouse-portal.js') }}" defer></script>
</body>
</html>
