{{-- AI ətir seçici üçün üzən düymə (FAB). Yalnız kataloq və məlumat səhifələrində —
     məhsul/səbət/checkout-da mobil "Al" zolağı ilə toqquşmasın, /advisor-da isə lazım deyil. --}}
@if(request()->routeIs('home', 'category', 'brands', 'brand.products', 'front.page.*', 'front.faq', 'front.bonus', 'front.installment', 'front.referral'))
    <div class="advisor-fab" id="advisorFab">
        <span class="advisor-fab__bubble" id="advisorFabBubble" hidden>
            {{ __('advisor_fab_bubble') }}
            <button type="button" class="advisor-fab__bubble-close" aria-label="{{ __('popup_close') }}">×</button>
        </span>
        <span class="advisor-fab__label" aria-hidden="true">{{ __('advisor_link') }}</span>
        <a href="{{ route('front.advisor') }}" class="advisor-fab__button" aria-label="{{ __('advisor_link') }}">
            <img src="{{ asset_v('frontend/images/advisor-bot.webp') }}" alt="" width="64" height="64" decoding="async">
            <span class="advisor-fab__badge" aria-hidden="true">AI</span>
        </a>
    </div>
    <script>
        // Balon: hər ziyarətdə (sessiya) bir dəfə, 4 san sonra; 10 san görünür. Cookie zolağı/popup açıqdırsa — gözləyir.
        (() => {
            const bubble = document.getElementById('advisorFabBubble');
            const key = 'ps_advisor_bubble';
            let storage = null;
            try { storage = window.sessionStorage; if (storage.getItem(key)) return; } catch (e) { /* bağlıdır — hər səhifədə göstər */ }
            const hide = () => { bubble.classList.remove('is-open'); setTimeout(() => { bubble.hidden = true; }, 250); };
            const show = () => {
                if (document.querySelector('.site-popup, #cookieBar:not([hidden])')) { setTimeout(show, 3000); return; }
                try { storage?.setItem(key, '1'); } catch (e) { /* bağlıdır */ }
                bubble.hidden = false;
                requestAnimationFrame(() => bubble.classList.add('is-open'));
                setTimeout(hide, 10000);
            };
            bubble.querySelector('button').addEventListener('click', hide);
            setTimeout(show, 4000);
        })();
    </script>
@endif
