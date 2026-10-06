{{-- Admin popup-ları (Admin → Sayt → Popup-lar). Seçim, tezlik və göstərmə — popup.js --}}
@php
    $popups = \App\Models\Popup::forVisitor(auth()->id(), \App\Models\Popup::currentPage());
@endphp
@if($popups->isNotEmpty())
    @php
        $popupData = [
            'popups' => $popups->map->toClient()->all(),
            'eventUrl' => route('popup.event', ['popup' => '__ID__']),
            'token' => csrf_token(),
            'text' => ['close' => __('popup_close'), 'never' => __('popup_never_show')],
        ];
    @endphp
    <link rel="stylesheet" href="{{ asset_v('frontend/css/components/popup.css') }}">
    <script type="application/json" id="popup-data">@json($popupData)</script>
    <script defer src="{{ asset_v('frontend/js/popup.js') }}"></script>
@endif
