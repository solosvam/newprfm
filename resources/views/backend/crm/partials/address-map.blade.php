{{--
  Admin ünvan formaları üçün xəritə (public/shared/address-map.js): lokasiya linki (WhatsApp / Google Maps) + nöqtə seçimi.
  Parametrlər: $id (prefiks; kök — "{id}Map"), $address, $city (input selektorları), $lat / $lng (köhnə dəyər — validasiya xətasından sonra).
--}}
@if($mapsKey = config('services.google_maps.key'))
    <div class="address-map" id="{{ $id }}Map" data-address-map data-key="{{ $mapsKey }}" data-lang="az"
         data-lat="#{{ $id }}-lat" data-lng="#{{ $id }}-lng" data-address="{{ $address }}" data-city="{{ $city }}"
         data-resolve-url="{{ route('admin.maps.resolve-link') }}"
         data-text-pick="Xəritədə nöqtəni seç" data-text-change="Xəritədəki nöqtəni dəyiş" data-text-hint="Xəritəyə basın və ya pin-i sürüşdürün."
         data-text-selected="Nöqtə seçildi" data-text-denied="Yer təyin olunmadı" data-text-error="Xəritə yüklənmədi (GOOGLE_MAPS_KEY?)" data-text-use="Bu ünvanı yaz" data-text-approx="Ünvan dəqiq tapılmadı — pin-i özünüz qoyun.">
        <label class="form-label" for="{{ $id }}-link">Lokasiya linki</label>
        <div class="input-group mb-1">
            <input type="text" class="form-control" id="{{ $id }}-link" data-map-link autocomplete="off" inputmode="url"
                   placeholder="WhatsApp lokasiyası və ya Google Maps linki">
            <button type="button" class="btn btn-outline-primary" data-map-link-apply>Xəritədə göstər</button>
        </div>
        <div class="text-danger small mb-1" data-map-link-error hidden></div>
        <div class="form-text mb-2">Müştərinin göndərdiyi lokasiyanın linkini yapışdırın — nöqtə xəritədə qoyulur, ünvan boşdursa avtomatik yazılır.</div>
        <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2" data-map-open>
            <i data-acorn-icon="pin" data-acorn-size="16"></i><span data-map-open-label>Xəritədə nöqtəni seç</span>
        </button>
        <div class="mt-2" data-map-panel hidden>
            <div data-map-canvas style="height: 300px; border-radius: 10px; overflow: hidden; border: 1px solid var(--separator);"></div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-map-locate>Mənim yerim</button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-map-clear>Nöqtəni sil</button>
            </div>
            <div class="text-muted mt-2" style="font-size: 13px" data-map-status></div>
        </div>
        <input type="hidden" id="{{ $id }}-lat" name="latitude" value="{{ $lat ?? '' }}">
        <input type="hidden" id="{{ $id }}-lng" name="longitude" value="{{ $lng ?? '' }}">
    </div>
@endif
