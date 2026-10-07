/**
 * Ünvan üçün xəritə (Google Maps): sayt (checkout, kredit ünvanı) və admin (CRM ünvan pəncərəsi) üçün ümumi.
 *
 * <div data-address-map data-key="…" data-lang="az" data-lat="#latitude" data-lng="#longitude"
 *      data-address="#address" data-city="#city" data-text-*="…">
 *   <button data-map-open>…</button>
 *   <div data-map-panel hidden><div data-map-canvas></div> … [data-map-locate] [data-map-clear] [data-map-status]</div>
 * </div>
 *
 * - Google skripti yalnız "Xəritədə seç" basılanda yüklənir (pulsuz limitə qənaət).
 * - Pin: xəritəyə basmaq, sürükləmək, "Mənim yerim" (brauzer geolokasiyası).
 * - Açılanda yazılmış ünvan (küçə + şəhər) Geocoding ilə tapılır; pin qoyulanda yerin ünvanı statusda göstərilir.
 * - Koordinatlar gizli sahələrə yazılır; boşdursa — nöqtə seçilməyib.
 * - Ünvan sahəsinin altında Google təklifləri (Places API (New) — AutocompleteSuggestion); seçiləndə küçə, şəhər
 *   və nöqtə doldurulur (xəritə açılmasa da). Skript yalnız sahəyə yazmağa başlayanda yüklənir.
 */
(function () {
    'use strict';

    const BAKU = { lat: 40.4093, lng: 49.8671 };

    // Saytın palitrası: bənövşəyi su, yumşaq fon; qaranlıq temada — tünd (yalnız "Xəritə" növünə aiddir, peykə yox)
    const STYLE_LIGHT = [
        { elementType: 'geometry', stylers: [{ color: '#f6f4f9' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#5b5466' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#ffffff' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#d6cdea' }] },
        { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#6b5a99' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
        { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#fbfaff' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#e6def5' }] },
        { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#cfc3e6' }] },
        { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#eeeaf4' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#e3eedf' }] },
        { featureType: 'poi', elementType: 'labels.icon', stylers: [{ saturation: -60 }, { lightness: 15 }] },
        { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#ebe6f2' }] },
        { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#c9bfdc' }] },
    ];
    const STYLE_DARK = [
        { elementType: 'geometry', stylers: [{ color: '#1c1724' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#a39dae' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#1c1724' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#2b2145' }] },
        { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#8a7cb8' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#2c2538' }] },
        { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#211b2b' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#3d3152' }] },
        { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#b9b1c7' }] },
        { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#231d2d' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#1f2a24' }] },
        { featureType: 'poi', elementType: 'labels.icon', stylers: [{ saturation: -70 }, { lightness: -30 }] },
        { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2a2333' }] },
        { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#3a3147' }] },
    ];
    const isDark = () => document.documentElement.dataset.theme === 'dark';
    let loader = null;

    function loadGoogle(key, lang) {
        if (window.google?.maps) return Promise.resolve();
        if (loader) return loader;
        loader = new Promise((resolve, reject) => {
            window.__addressMapReady = resolve;
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key)
                + '&libraries=places&language=' + encodeURIComponent(lang || 'az') + '&region=AZ&loading=async&callback=__addressMapReady';
            script.async = true;
            script.onerror = () => { loader = null; reject(new Error('maps')); };
            document.head.appendChild(script);
        });
        return loader;
    }

    function init(root) {
        if (root.dataset.mapInit) return;
        root.dataset.mapInit = '1';

        const $ = (selector) => (selector ? document.querySelector(selector) : null);
        const latInput = $(root.dataset.lat);
        const lngInput = $(root.dataset.lng);
        const addressInput = $(root.dataset.address);
        const citySelect = $(root.dataset.city);
        const openButton = root.querySelector('[data-map-open]');
        const panel = root.querySelector('[data-map-panel]');
        const canvas = root.querySelector('[data-map-canvas]');
        const status = root.querySelector('[data-map-status]');
        const text = (name) => root.dataset['text' + name.charAt(0).toUpperCase() + name.slice(1)] || '';

        let map = null;
        let marker = null;
        let geocoder = null;

        const current = () => {
            const lat = parseFloat(latInput?.value);
            const lng = parseFloat(lngInput?.value);
            return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
        };

        function syncButton() {
            const has = !!current();
            root.classList.toggle('has-location', has);
            if (openButton) openButton.querySelector('[data-map-open-label]').textContent = has ? text('change') : text('pick');
        }

        function setStatus(message) {
            if (status) status.textContent = message || '';
        }

        function place(position, { pan = true, describe = true } = {}) {
            const lat = Math.round(position.lat * 1e7) / 1e7;
            const lng = Math.round(position.lng * 1e7) / 1e7;
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            latInput?.dispatchEvent(new Event('change', { bubbles: true }));
            if (!marker) {
                marker = new google.maps.Marker({ map, position: { lat, lng }, draggable: true });
                marker.addListener('dragend', () => {
                    const p = marker.getPosition();
                    place({ lat: p.lat(), lng: p.lng() }, { pan: false });
                });
            } else {
                marker.setPosition({ lat, lng });
            }
            if (pan) { map.panTo({ lat, lng }); if (map.getZoom() < 16) map.setZoom(17); }
            syncButton();
            setStatus(text('selected'));
            if (describe && geocoder) {
                geocoder.geocode({ location: { lat, lng } }).then(({ results }) => {
                    const result = bestResult(results);
                    if (result) fillFromResult(result);
                }).catch(() => {});
            }
        }

        /**
         * Google eyni nöqtə üçün bir neçə nəticə qaytarır; bəzilərində küçə adı yalnız rusca olur ("75 Истиглал"),
         * digərində azərbaycanca ("75 İstiqlal"). Seçim: plus kod deyil, küçəsi var, küçə adı kiril deyil, ev nömrəsi var.
         */
        function bestResult(results) {
            const cyrillic = /[\u0400-\u04FF]/;
            const score = (r) => {
                const route = component(r, 'route')?.long_name || '';
                return (route ? 4 : 0) + (route && !cyrillic.test(route) ? 8 : 0)
                    + (component(r, 'street_number') ? 2 : 0) + (r.types.includes('street_address') ? 1 : 0);
            };
            const usable = (results || []).filter((r) => !r.types.includes('plus_code'));
            return usable.reduce((best, r) => (!best || score(r) > score(best) ? r : best), null);
        }

        // "ə, ı, ö…" fərqi olmadan müqayisə: "Baku" = "Bakı", "Naxçıvan" = "Nakhchivan" deyil, amma "Naxcivan" = "Naxçıvan"
        const norm = (value) => (value || '').toLowerCase()
            .replace(/ə/g, 'e').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ü/g, 'u').replace(/ğ/g, 'g').replace(/ş/g, 's').replace(/ç/g, 'c')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9а-я]/g, '');
        const component = (result, type) => result.address_components.find((c) => c.types.includes(type));

        /** Tapılan yerin küçə + ev nömrəsi → ünvan sahəsi, şəhər → şəhər seçimi */
        function fillFromResult(result) {
            const route = component(result, 'route')?.long_name;
            const number = component(result, 'street_number')?.long_name;
            const area = component(result, 'neighborhood')?.long_name || component(result, 'sublocality')?.long_name;
            const street = route ? [route, number].filter(Boolean).join(' ') : (area || '');
            const locality = component(result, 'locality')?.long_name || component(result, 'administrative_area_level_1')?.long_name || '';

            setStatus(text('selected') + ': ' + result.formatted_address);

            const write = () => {
                if (addressInput && street) {
                    addressInput.value = street;
                    addressInput.dataset.mapFilled = street;
                    addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                selectCity(locality);
            };
            // müştərinin özü yazdığı ünvanı silmirik — təklif edirik
            const own = addressInput?.value.trim();
            if (!own || own === addressInput.dataset.mapFilled) {
                write();
                return;
            }
            if (status && street && own !== street) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'address-map__use';
                button.textContent = text('use') || '↵';
                button.addEventListener('click', () => { write(); button.remove(); });
                status.append(' ', button);
            }
        }

        function selectCity(name) {
            if (!citySelect || !name) return;
            const target = norm(name).replace(/(city|seheri|rayonu|rayon|sahari)$/, '');
            const option = [...citySelect.options].find((o) => o.value && (norm(o.textContent) === target || norm(o.textContent).startsWith(target) || target.startsWith(norm(o.textContent))));
            if (!option || citySelect.value === option.value) return;
            citySelect.value = option.value;
            citySelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (window.jQuery) window.jQuery(citySelect).trigger('change');   // select2
        }

        function clear() {
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            marker?.setMap(null);
            marker = null;
            syncButton();
            setStatus(text('hint'));
        }

        function locate() {
            if (!navigator.geolocation) { setStatus(text('denied')); return; }
            setStatus('…');
            navigator.geolocation.getCurrentPosition(
                (pos) => place({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
                () => setStatus(text('denied')),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        async function open() {
            panel.hidden = !panel.hidden;
            if (panel.hidden) return;
            if (map) { google.maps.event.trigger(map, 'resize'); return; }
            setStatus('…');
            try {
                await loadGoogle(root.dataset.key, root.dataset.lang);
            } catch (e) {
                setStatus(text('error'));
                return;
            }
            const start = current();
            map = new google.maps.Map(canvas, {
                center: start || BAKU, zoom: start ? 17 : 12,
                // Xəritə / Peyk — binanı peyk görüntüsündə tapmaq daha asandır
                mapTypeControl: true,
                mapTypeControlOptions: {
                    style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
                    position: google.maps.ControlPosition.TOP_LEFT,
                    mapTypeIds: ['roadmap', 'hybrid'],
                },
                streetViewControl: false, fullscreenControl: true, clickableIcons: false,
                styles: isDark() ? STYLE_DARK : STYLE_LIGHT,
                gestureHandling: 'greedy',
            });
            geocoder = new google.maps.Geocoder();
            // sayt temasını dəyişəndə xəritə də dəyişsin
            new MutationObserver(() => map.setOptions({ styles: isDark() ? STYLE_DARK : STYLE_LIGHT }))
                .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            map.addListener('click', (event) => place({ lat: event.latLng.lat(), lng: event.latLng.lng() }, { pan: false }));
            if (start) {
                place(start, { pan: false });
                return;
            }
            setStatus(text('hint'));
            // yazılmış ünvan varsa — xəritədə tap
            if (addressInput?.value?.trim()) findTyped();
        }

        // ünvan yazılanda (xəritə açıqdırsa) — xəritədə tap və pin qoy; müştəri sonra dəqiqləşdirir
        let typingTimer = null;
        function findTyped() {
            const value = addressInput?.value.trim();
            if (!map || !geocoder || !value || value === addressInput.dataset.mapFilled) return;
            const city = citySelect?.selectedOptions?.[0]?.value ? citySelect.selectedOptions[0].textContent.trim() : '';
            geocoder.geocode({
                address: [value, city].filter(Boolean).join(', '),
                componentRestrictions: { country: 'AZ' },
            }).then(({ results }) => {
                const hit = results?.[0];
                if (!hit) return;
                const exact = hit.types.some((t) => ['street_address', 'premise', 'route', 'establishment', 'point_of_interest'].includes(t));
                const loc = hit.geometry.location;
                if (exact) {
                    // ünvanı biz yazmırıq (müştəri yazıb) — yalnız nöqtə
                    place({ lat: loc.lat(), lng: loc.lng() }, { describe: false });
                    setStatus(text('selected') + ': ' + hit.formatted_address);
                    // şəhər seçilməyibsə — tapılan şəhər
                    if (!citySelect?.value) selectCity(component(hit, 'locality')?.long_name || component(hit, 'administrative_area_level_1')?.long_name);
                } else {
                    // küçə dəqiq tapılmadı (məs. yalnız şəhər) — xəritə ora gedir, pin-i müştəri özü qoyur
                    map.setCenter(loc);
                    map.setZoom(hit.types.includes('locality') ? 13 : 15);
                    setStatus(text('approx') || text('hint'));
                    if (!citySelect?.value) selectCity(component(hit, 'locality')?.long_name || component(hit, 'administrative_area_level_1')?.long_name);
                }
            }).catch(() => {});
        }
        const scheduleFind = () => { clearTimeout(typingTimer); typingTimer = setTimeout(findTyped, 700); };
        addressInput?.addEventListener('input', (event) => { if (event.isTrusted) scheduleFind(); });
        citySelect?.addEventListener('change', (event) => { if (event.isTrusted) scheduleFind(); });
        if (window.jQuery && citySelect) window.jQuery(citySelect).on('select2:select', scheduleFind);

        // ---- Ünvan təklifləri (Google Places API (New)) ----
        const suggest = setupSuggestions();

        function setupSuggestions() {
            if (!addressInput) return null;
            const box = document.createElement('ul');
            box.className = 'address-suggest';
            box.setAttribute('role', 'listbox');
            box.hidden = true;
            addressInput.setAttribute('autocomplete', 'off');
            addressInput.parentElement.style.position = 'relative';
            addressInput.insertAdjacentElement('afterend', box);

            let timer = null;
            let token = null;
            let items = [];
            let active = -1;
            let lastQuery = '';

            const hide = () => { box.hidden = true; active = -1; };
            const highlight = (index) => {
                active = index;
                [...box.children].forEach((li, i) => li.classList.toggle('is-active', i === index));
            };

            async function fetchSuggestions() {
                const query = addressInput.value.trim();
                if (query.length < 3 || query === addressInput.dataset.mapFilled || query === lastQuery) return;
                lastQuery = query;
                try {
                    await loadGoogle(root.dataset.key, root.dataset.lang);
                    const { AutocompleteSuggestion, AutocompleteSessionToken } = await google.maps.importLibrary('places');
                    token = token || new AutocompleteSessionToken();
                    const city = citySelect?.selectedOptions?.[0]?.value ? citySelect.selectedOptions[0].textContent.trim() : '';
                    const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                        input: city ? query + ', ' + city : query,
                        includedRegionCodes: ['az'],
                        language: root.dataset.lang || 'az',
                        region: 'az',
                        locationBias: map ? map.getCenter() : BAKU,
                        sessionToken: token,
                    });
                    if (addressInput.value.trim() !== query) return;   // istifadəçi artıq başqa şey yazıb
                    items = (suggestions || []).map((s) => s.placePrediction).filter(Boolean).slice(0, 5);
                    box.innerHTML = '';
                    items.forEach((prediction, i) => {
                        const li = document.createElement('li');
                        li.setAttribute('role', 'option');
                        const main = document.createElement('strong');
                        main.textContent = prediction.mainText?.text || prediction.text.text;
                        const sub = document.createElement('small');
                        sub.textContent = prediction.secondaryText?.text || '';
                        li.append(main, sub);
                        li.addEventListener('mousedown', (event) => { event.preventDefault(); choose(i); });
                        box.appendChild(li);
                    });
                    box.hidden = items.length === 0;
                    active = -1;
                } catch (e) {
                    hide();   // Places API açarda aktiv deyilsə — sadəcə təklif yoxdur
                }
            }

            async function choose(index) {
                const prediction = items[index];
                if (!prediction) return;
                hide();
                try {
                    const place = prediction.toPlace();
                    await place.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents'] });
                    token = null;   // seçimlə sessiya bitir
                    const comps = place.addressComponents || [];
                    const get = (type) => comps.find((c) => c.types.includes(type));
                    const route = get('route')?.longText;
                    const number = get('street_number')?.longText;
                    const street = route ? [route, number].filter(Boolean).join(' ') : (prediction.mainText?.text || '');
                    addressInput.value = street;
                    addressInput.dataset.mapFilled = street;
                    lastQuery = street;
                    selectCity(get('locality')?.longText || get('administrative_area_level_1')?.longText);
                    if (place.location) {
                        const point = { lat: place.location.lat(), lng: place.location.lng() };
                        if (map) {
                            place.location && placeMarker(point, place.formattedAddress);
                        } else {
                            // xəritə açılmayıb — yalnız koordinatlar
                            if (latInput) latInput.value = Math.round(point.lat * 1e7) / 1e7;
                            if (lngInput) lngInput.value = Math.round(point.lng * 1e7) / 1e7;
                            syncButton();
                        }
                    }
                } catch (e) {
                    addressInput.value = prediction.mainText?.text || prediction.text.text;
                }
            }

            addressInput.addEventListener('input', (event) => {
                if (!event.isTrusted) return;
                clearTimeout(timer);
                timer = setTimeout(fetchSuggestions, 300);
            });
            addressInput.addEventListener('keydown', (event) => {
                if (box.hidden) return;
                if (event.key === 'ArrowDown') { event.preventDefault(); highlight(Math.min(active + 1, items.length - 1)); }
                else if (event.key === 'ArrowUp') { event.preventDefault(); highlight(Math.max(active - 1, 0)); }
                else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); choose(active); }
                else if (event.key === 'Escape') hide();
            });
            addressInput.addEventListener('blur', () => setTimeout(hide, 150));
            return { hide };
        }

        function placeMarker(point, label) {
            place(point, { describe: false });
            setStatus(text('selected') + (label ? ': ' + label : ''));
        }

        openButton?.addEventListener('click', open);
        root.querySelector('[data-map-locate]')?.addEventListener('click', () => { if (map) locate(); });
        root.querySelector('[data-map-clear]')?.addEventListener('click', clear);
        // forma başqa ünvanla doldurulanda (CRM pəncərəsi) — xəritəni yenilə
        root.addEventListener('address-map:refresh', () => {
            syncButton();
            const p = current();
            if (map && p) place(p, { describe: false });
            else if (map && !p) { marker?.setMap(null); marker = null; map.setCenter(BAKU); map.setZoom(12); setStatus(text('hint')); }
        });
        syncButton();
    }

    function initAll() {
        document.querySelectorAll('[data-address-map]').forEach(init);
    }
    window.AddressMap = { init: initAll };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
    else initAll();
})();
