{{--
  Hədiyyə kartının üzü (bank kartı nisbəti 85.6 × 54).
  $amount — AZN; $code — formatlanmış kod və ya null (ödənişdən əvvəl ••••); $expires — "15.11.2027" və ya null;
  $design — GiftCardService::DESIGNS (standard | signature). Endirilən açıqca eyni dizaynla çəkilir (pages/gift-card.js).
--}}
@php $design = $design ?? 'standard'; @endphp
<div class="gc-card gc-card--{{ $design }}" data-gc-card data-design="{{ $design }}">
    @if($design === 'signature')
        {{-- Signature — Premium: bənövşəyi fon, qızılı loqo / məbləğ, nazik çərçivə və gilyoş naxışı --}}
        <svg class="gc-card__deco" viewBox="0 0 856 540" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="gcGold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#b8893b"/><stop offset=".42" stop-color="#f6e3a6"/>
                    <stop offset=".68" stop-color="#c99a45"/><stop offset="1" stop-color="#efd38c"/>
                </linearGradient>
            </defs>
            {{-- Gilyoş naxışı və ikon eyni mərkəzdə (672, 236): ikon 300 vahid hündürlükdə, 480×760-dan miqyaslanır --}}
            <g fill="none" stroke="#d4af5f" stroke-width="1" opacity=".14">
                @for($i = 0; $i < 36; $i++)
                    <ellipse cx="672" cy="236" rx="190" ry="66" transform="rotate({{ $i * 5 }} 672 236)"/>
                @endfor
            </g>
            <g data-gc-icon transform="translate(577.26 86) scale(0.39474)" fill="url(#gcGold)">
                @include('frontend.gift-card._icon')
            </g>
            <rect x="22" y="22" width="812" height="496" rx="16" fill="none" stroke="#d4af5f" stroke-width="1.2" opacity=".55"/>
        </svg>
    @endif
    <div class="gc-card__logo">@include('frontend.partials.logo')</div>
    <div class="gc-card__label">{{ __('gift_card_title') }}</div>
    <div class="gc-card__amount"><span data-gc-amount>{{ (int) $amount }}</span><small>AZN</small></div>
    <div class="gc-card__bottom">
        <div class="gc-card__code"><span>{{ __('gift_card_code_label') }}</span><b data-gc-code>{{ $code ?? '••••-••••-••••' }}</b></div>
        @if(!empty($expires))
            <div class="gc-card__exp"><span>{{ __('gift_card_valid_until') }}</span><b>{{ $expires }}</b></div>
        @endif
    </div>
</div>
