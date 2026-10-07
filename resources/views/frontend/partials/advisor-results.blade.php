{{-- AI məsləhətçinin nəticəsi: məhsul kartı + niyə uyğun olduğu (AdvisorController::recommend) --}}
<div class="grid advisor-grid">
    @foreach($items as $item)
        <div class="advisor-pick">
            @include('frontend.includes.product-card', ['product' => $item['product'], 'hideBrand' => false])
            @if($item['reason'])
                <p class="advisor-pick__reason">{{ $item['reason'] }}</p>
            @endif
        </div>
    @endforeach
</div>
