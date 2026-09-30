// Banner slayderi: avtomatik keçid (data-interval, ms — Admin → Ayarlar), nöqtələr, oxlar, klaviatura.
// Sürüşdürmə (toxunma/trackpad) CSS scroll-snap ilə işləyir; JS yalnız mövqeyi izləyir.
// Avtomatik keçid dayanır: siçan üstündə, fokusda, tab görünməyəndə, "reduced motion"da.
(() => {
    'use strict';
    const DEFAULT_INTERVAL = 5000;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-banner-slider]').forEach((slider) => {
        const INTERVAL = Math.max(2000, Number(slider.dataset.interval) || DEFAULT_INTERVAL);
        const track = slider.querySelector('[data-slider-track]');
        const slides = [...track.children];
        const dots = [...slider.querySelectorAll('[data-slider-dot]')];
        let index = 0;
        let timer = null;
        let paused = false;

        const go = (i) => {
            index = (i + slides.length) % slides.length;
            track.scrollTo({ left: index * track.clientWidth, behavior: reduceMotion ? 'auto' : 'smooth' });
        };
        const paint = () => dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === index)));

        // Hansı slayd görünür (əl ilə sürüşdürmə daxil)
        let raf = 0;
        track.addEventListener('scroll', () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
                if (i !== index) { index = Math.min(Math.max(i, 0), slides.length - 1); paint(); }
            });
        }, { passive: true });

        const stop = () => { clearInterval(timer); timer = null; };
        const start = () => {
            stop();
            if (reduceMotion || paused || document.hidden || !slider.offsetParent) return; // gizli slayder (digər cihaz) fırlanmır
            timer = setInterval(() => go(index + 1), INTERVAL);
        };
        const restart = () => { stop(); start(); };

        slider.querySelector('[data-slider-prev]')?.addEventListener('click', () => { go(index - 1); restart(); });
        slider.querySelector('[data-slider-next]')?.addEventListener('click', () => { go(index + 1); restart(); });
        dots.forEach((dot, i) => dot.addEventListener('click', () => { go(i); restart(); }));
        track.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') { event.preventDefault(); go(index - 1); }
            if (event.key === 'ArrowRight') { event.preventDefault(); go(index + 1); }
        });

        slider.addEventListener('mouseenter', () => { paused = true; stop(); });
        slider.addEventListener('mouseleave', () => { paused = false; start(); });
        slider.addEventListener('focusin', () => { paused = true; stop(); });
        slider.addEventListener('focusout', () => { paused = false; start(); });
        track.addEventListener('touchstart', stop, { passive: true });
        track.addEventListener('touchend', () => setTimeout(start, 800), { passive: true });
        document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
        window.addEventListener('resize', () => { track.scrollLeft = index * track.clientWidth; restart(); });

        paint();
        start();
    });
})();
