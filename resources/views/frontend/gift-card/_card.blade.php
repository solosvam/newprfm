{{--
  Hədiyyə kartının üzü (standart dizayn, bank kartı nisbəti 85.6 × 54).
  $amount — AZN; $code — formatlanmış kod və ya null (ödənişdən əvvəl ••••); $expires — "11.2027" və ya null.
  Dizayn sonra seçiləcək (görüləcək işlər) — hələlik saytın rəngi və loqosu.
--}}
<div class="gc-card" data-gc-card>
    <div class="gc-card__logo">@include('frontend.partials.logo')</div>
    <div class="gc-card__label">{{ __('gift_card_title') }}</div>
    <div class="gc-card__amount"><span data-gc-amount>{{ (int) $amount }}</span><small>AZN</small></div>
    <div class="gc-card__bottom">
        <div class="gc-card__code" data-gc-code>{{ $code ?? '••••-••••-••••' }}</div>
        @if(!empty($expires))
            <div class="gc-card__exp"><span>{{ __('gift_card_valid_until') }}</span><b>{{ $expires }}</b></div>
        @endif
    </div>
</div>
