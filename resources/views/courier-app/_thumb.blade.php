{{-- Məhsulun kiçik şəkli; basanda tam ekranda böyüyür (app.js) --}}
@php $img = $product?->images->first(); @endphp
@if($img)
    @php $src = asset('frontend/uploads/products/'.$img->image); @endphp
    <button type="button" class="thumb" data-zoom="{{ $src }}" aria-label="Şəkli böyüt: {{ $product->name }}">
        <img src="{{ $src }}" alt="" loading="lazy">
    </button>
@else
    <span class="thumb thumb--empty" aria-hidden="true">@include('courier-app._icon', ['name' => 'box'])</span>
@endif
