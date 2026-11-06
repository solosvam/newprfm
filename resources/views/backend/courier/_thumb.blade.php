{{-- Məhsulun kiçik şəkli; basanda böyüyür (courier.js) --}}
@php $img = $product?->images->first(); @endphp
@if($img)
    @php $src = asset('frontend/uploads/products/'.$img->image); @endphp
    <button type="button" class="courier-thumb" data-zoom="{{ $src }}" aria-label="Şəkli böyüt: {{ $product->name }}">
        <img src="{{ $src }}" alt="" loading="lazy">
    </button>
@endif
