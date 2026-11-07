// Kuryerin günlük marşrutu (admin → Kuryerlər → kuryer → "Yer tarixçəsi"): Leaflet + OpenStreetMap.
// Nöqtələr [lat, lng, "HH:MM", dəqiqlik] — data-points; saat sırası ilə xətlə birləşir.
// Başlanğıc — yaşıl, son yer — qırmızı, aradakılar — kiçik nöqtələr (basanda saat). Siyahıdan nöqtəyə basanda xəritə oraya gedir.
(function () {
    'use strict';
    const box = document.querySelector('[data-courier-route]');
    if (!box || !window.L) return;

    let points = [];
    try { points = JSON.parse(box.dataset.points || '[]'); } catch (e) { return; }
    if (!points.length) return;

    const map = L.map(box, { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const latlngs = points.map(p => [p[0], p[1]]);
    // Görüntü laylardan əvvəl təyin olunur (Leaflet xətləri və nöqtələri yalnız bundan sonra çəkir)
    if (latlngs.length > 1) map.fitBounds(L.latLngBounds(latlngs), { padding: [24, 24], maxZoom: 16 });
    else map.setView(latlngs[0], 16);
    const label = p => p[2] + (p[3] ? ' · ±' + p[3] + ' m' : '');

    // Marşrut xətti
    if (latlngs.length > 1) {
        L.polyline(latlngs, { color: '#7b46b0', weight: 4, opacity: 0.85 }).addTo(map);
    }

    // Nöqtələr: aradakılar kiçik, başlanğıc və son böyük
    const markers = points.map((p, i) => {
        const first = i === 0;
        const last = i === points.length - 1;
        const marker = L.circleMarker([p[0], p[1]], {
            radius: first || last ? 9 : 4,
            color: '#ffffff',
            weight: first || last ? 3 : 1,
            fillColor: last ? '#d63939' : (first ? '#2fb344' : '#7b46b0'),
            fillOpacity: 1,
        }).addTo(map);
        marker.bindTooltip((first ? 'Başlanğıc · ' : last ? 'Son yer · ' : '') + label(p), { direction: 'top' });
        return marker;
    });
    markers[markers.length - 1].bringToFront();
    // Səhifə tam yüklənəndə ölçü yenidən hesablanır (kartın eni sonradan dəyişə bilər — boz sahə qalmasın)
    window.addEventListener('load', () => map.invalidateSize());

    // Təxmini məsafə (düz xətlər üzrə)
    const distance = document.querySelector('[data-route-distance]');
    if (distance) {
        let meters = 0;
        for (let i = 1; i < latlngs.length; i++) meters += map.distance(latlngs[i - 1], latlngs[i]);
        distance.textContent = '≈ ' + (meters / 1000).toFixed(1) + ' km';
    }

    // Siyahıdan nöqtə — xəritədə göstər
    document.addEventListener('click', event => {
        const row = event.target.closest('[data-route-point]');
        if (!row) return;
        const i = Number(row.dataset.routePoint);
        if (!markers[i]) return;
        map.setView(latlngs[i], 17);
        markers[i].openTooltip();
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
})();
