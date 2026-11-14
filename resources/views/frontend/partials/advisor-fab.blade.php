{{-- AI ətir seçici üçün üzən düymə (FAB). Yalnız kataloq və məlumat səhifələrində —
     məhsul/səbət/checkout-da mobil "Al" zolağı ilə toqquşmasın, /advisor-da isə lazım deyil. --}}
@if(request()->routeIs('home', 'category', 'brands', 'brand.products', 'front.page.*', 'front.faq', 'front.bonus', 'front.installment', 'front.referral'))
    <div class="advisor-fab" id="advisorFab">
        {{-- WhatsApp: robotun üstündə; nömrə Ayarlar → Əlaqə məlumatları (contact_whatsapp), boşdursa düymə yoxdur --}}
        @if($whatsappUrl = app(\App\Services\ContactInfo::class)->whatsappUrl())
            <a href="{{ $whatsappUrl }}" class="advisor-fab__whatsapp" target="_blank" rel="noopener" aria-label="WhatsApp">
                <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16.04 3C9.4 3 4 8.38 4 15c0 2.12.56 4.19 1.62 6.01L4 29l8.2-1.6A12.07 12.07 0 0 0 16.04 27C22.66 27 28 21.62 28 15S22.66 3 16.04 3Zm0 21.9c-1.8 0-3.56-.48-5.1-1.4l-.37-.22-4.87.95.97-4.73-.24-.38A9.84 9.84 0 0 1 6.1 15c0-5.47 4.46-9.92 9.94-9.92 5.47 0 9.92 4.45 9.92 9.92s-4.45 9.9-9.92 9.9Zm5.45-7.42c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.47 1.07 2.88 1.22 3.08.15.2 2.1 3.2 5.08 4.49.71.3 1.27.49 1.7.63.71.23 1.36.2 1.88.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35Z"/></svg>
            </a>
        @endif
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
