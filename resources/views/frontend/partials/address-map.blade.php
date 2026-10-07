{{-- Ünvan üçün xəritə (public/shared/address-map.js). GOOGLE_MAPS_KEY yoxdursa — göstərilmir.
     Koordinatlar #latitude/#longitude gizli sahələrinə yazılır (checkout.js, credit-address.js göndərir). --}}
@if($mapsKey = config('services.google_maps.key'))
    <div class="address-map" data-address-map data-key="{{ $mapsKey }}" data-lang="{{ app()->getLocale() }}"
         data-lat="#latitude" data-lng="#longitude" data-address="#address" data-city="#city"
         data-text-pick="{{ __('map_pick') }}" data-text-change="{{ __('map_change') }}" data-text-hint="{{ __('map_hint') }}"
         data-text-selected="{{ __('map_selected') }}" data-text-denied="{{ __('map_denied') }}" data-text-error="{{ __('map_error') }}" data-text-use="{{ __('map_use_address') }}" data-text-approx="{{ __('map_approx') }}">
        <button type="button" class="address-map__open" data-map-open>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span data-map-open-label>{{ __('map_pick') }}</span>
            <small>{{ __('map_optional') }}</small>
        </button>
        <div class="address-map__panel" data-map-panel hidden>
            <div class="address-map__canvas" data-map-canvas></div>
            <div class="address-map__bar">
                <button type="button" class="address-map__btn" data-map-locate>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                    {{ __('map_locate') }}
                </button>
                <button type="button" class="address-map__btn address-map__btn--muted" data-map-clear>{{ __('map_clear') }}</button>
            </div>
            <p class="address-map__status" data-map-status aria-live="polite"></p>
        </div>
        <input type="hidden" id="latitude">
        <input type="hidden" id="longitude">
    </div>
    @once
        <script defer src="{{ asset_v('shared/address-map.js') }}"></script>
    @endonce
@endif
