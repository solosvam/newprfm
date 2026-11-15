{{--
  Səbət və checkout: promo kodun altında "Hədiyyə kartı". Hesabı giftcard.js aparır (window.ParfumGiftCard),
  server checkout-da kartı yenidən yoxlayır (CheckoutController::store, GiftCardService).
--}}
@php
    $giftCardCode = session('gift_card_code');
    $giftCard = $giftCardCode ? \App\Models\GiftCard\GiftCard::where('code', $giftCardCode)->first() : null;
    if ($giftCard && !$giftCard->isUsable()) {
        session()->forget('gift_card_code');
        $giftCard = null;
    }
@endphp
<div class="promo promo--giftcard"
     data-giftcard
     data-apply-url="{{ route('gift-card.apply') }}"
     data-remove-url="{{ route('gift-card.remove') }}"
     data-code="{{ $giftCard?->formattedCode() }}"
     data-amount="{{ $giftCard ? (float) $giftCard->amount : 0 }}"
     data-burn="{{ __('gift_card_burn_note', ['amount' => ':amount']) }}">

    <details class="promo__details" @if($giftCard) hidden @endif>
        <summary class="promo__toggle">{{ __('gift_card_have') }}</summary>
        <form class="promo__form" novalidate>
            <input type="text" name="code" class="promo__input"
                   placeholder="{{ __('gift_card_placeholder') }}"
                   autocomplete="off" autocapitalize="characters" maxlength="30">
            <button type="submit" class="promo__apply">{{ __('gift_card_apply') }}</button>
        </form>
    </details>

    <div class="promo__applied" @unless($giftCard) hidden @endunless>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M12 8H8.5a2.5 2.5 0 1 1 0-5C11 3 12 8 12 8zM12 8h3.5a2.5 2.5 0 1 0 0-5C13 3 12 8 12 8z"/></svg>
        <span class="promo__code">{{ $giftCard?->formattedCode() }}</span>
        <span class="promo__amount"></span>
        <button type="button" class="promo__remove" aria-label="{{ __('gift_card_remove') }}">×</button>
    </div>
    <p class="promo__error" role="alert" hidden></p>
    <p class="promo__referral" data-giftcard-note hidden></p>
</div>
