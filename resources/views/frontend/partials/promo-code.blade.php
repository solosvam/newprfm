@php($promoCode = session('promo_code'))
<div class="promo"
     data-promo
     data-apply-url="{{ route('promo.apply') }}"
     data-remove-url="{{ route('promo.remove') }}"
     data-code="{{ $promoCode }}">

    <details class="promo__details" @if($promoCode) hidden @endif>
        <summary class="promo__toggle">{{ __('promo_have_code') }}</summary>
        <form class="promo__form" novalidate>
            <input type="text" name="code" class="promo__input"
                   placeholder="{{ __('promo_placeholder') }}"
                   autocomplete="off" autocapitalize="characters" maxlength="50">
            <button type="submit" class="promo__apply">{{ __('promo_apply') }}</button>
        </form>
    </details>

    <div class="promo__applied" @unless($promoCode) hidden @endunless>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
        <span class="promo__code">{{ $promoCode }}</span>
        <span class="promo__amount"></span>
        <button type="button" class="promo__remove" aria-label="{{ __('promo_remove') }}">×</button>
    </div>

    <p class="promo__error" role="alert" hidden></p>
</div>
