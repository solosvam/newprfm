{{-- Referal proqramının şərtləri (ayarlardan): /profile/referral və ictimai /referral səhifəsi.
     Lazım olan: $settings (ReferralSettings), $money, $isDiscount, $limit, $minOrder --}}
<ul>
    <li>{{ __('referral_term_new_customers') }}</li>
    <li>{{ __('referral_term_delivered') }}</li>
    @if($minOrder !== null)
        <li>{{ __('referral_term_min_order', ['amount' => $money($minOrder)]) }}</li>
    @endif
    @unless($settings->installmentAllowed())
        <li>{{ __('referral_term_no_installment') }}</li>
    @endunless
    @if($isDiscount)
        <li>{{ __($settings->discountCombinesWithPromo() ? 'referral_term_promo_combines' : 'referral_term_promo_not_combines') }}</li>
    @endif
    @if($settings->referrerExpiryDays())
        <li>{{ __('referral_term_expiry', ['referrer' => $settings->referrerExpiryDays(), 'invitee' => $settings->inviteeExpiryDays()]) }}</li>
    @endif
    @if($limit)
        <li>{{ __('referral_term_limit', ['count' => $limit]) }}</li>
    @endif
    <li>{{ __('referral_term_cancelled') }}</li>
    <li>{{ __('referral_term_self') }}</li>
</ul>
