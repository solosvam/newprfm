{{-- Cookie bildirişi: yalnız zəruri cookie-lər (sessiya, giriş, referal, axtarış) — seçim lazım deyil, məlumat xarakterlidir.
     "Aydındır" — localStorage-da yadda qalır (bağlıdırsa, sessiya ərzində gizlənir). Analytics/Pixel əlavə olunsa, "İmtina" seçimi lazımdır. --}}
<div class="cookie-bar" id="cookieBar" role="region" aria-label="Cookie" hidden>
    <p class="cookie-bar__text">
        {{ __('cookie_text') }}
        <a href="{{ route('front.page.privacy') }}">{{ __('cookie_more') }}</a>
    </p>
    <button type="button" class="btn btn-dark cookie-bar__ok" id="cookieBarOk">{{ __('cookie_ok') }}</button>
</div>
<script>
    (() => {
        const bar = document.getElementById('cookieBar');
        const key = 'ps_cookie_ok';
        let accepted = false;
        try { accepted = localStorage.getItem(key) === '1'; } catch (e) { /* bağlıdır */ }
        if (accepted) { bar.remove(); return; }
        // səhifə açılan kimi yox — məzmun görünəndən sonra, sakit
        // admin popup-u açıqdırsa (o da aşağıda ola bilər) — bağlanandan sonra
        const show = () => {
            if (document.querySelector('.site-popup')) { setTimeout(show, 2000); return; }
            bar.hidden = false;
            requestAnimationFrame(() => bar.classList.add('is-open'));
        };
        setTimeout(show, 1200);
        document.getElementById('cookieBarOk').addEventListener('click', () => {
            try { localStorage.setItem(key, '1'); } catch (e) { /* bağlıdır */ }
            bar.classList.remove('is-open');
            setTimeout(() => bar.remove(), 250);
        });
    })();
</script>
