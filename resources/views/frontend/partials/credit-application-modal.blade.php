@php
    $creditLocale = in_array(app()->getLocale(), ['az', 'ru', 'en'], true) ? app()->getLocale() : 'az';
    $creditCopy = [
        'title' => __('credit_modal_title'),
        'subtitle' => __('credit_modal_subtitle'),
        'size' => __('credit_modal_size'),
        'period' => __('credit_modal_period'),
        'price' => __('product_price'),
        'monthly' => __('credit_modal_monthly'),
        'total' => __('credit_modal_total'),
        'rules' => __('credit_modal_rules'),
        'accept' => __('credit_modal_accept'),
        'submit' => __('credit_address_continue'),
        'close' => __('common_close'),
        'success' => __('credit_application_success'),
        'error' => __('credit_modal_error'),
        'month' => __('product_month'),
        'free' => __('product_interest_free'),
        'scrollHint' => __('credit_modal_scroll_hint'),
    ];
    // Məhsul kartı: cins → həcm → konsentrasiya. Cins qısa ("Qadın üçün" → "Qadın"), konsentrasiya tam ("Eau de Parfum")
    $creditImage = $product->images->first();
    $creditGender = $product->genders->first();
    // ikon üçün: qadın / kişi / unisex (bazada "Qadın üçün" və "Qadın", "Kişi üçün" və "Kişi" ayrı-ayrıdır)
    $creditGenderKind = match (true) {
        !$creditGender => null,
        (bool) preg_match('/qadın|women/iu', $creditGender->name_az.' '.$creditGender->name_en) => 'female',
        (bool) preg_match('/kişi|\bmen\b/iu', $creditGender->name_az.' '.$creditGender->name_en) => 'male',
        default => 'unisex',
    };
    $creditGender = $creditGender ? preg_replace(['/\s+üçün$/u', '/^for\s+/iu'], '', $creditGender->{'name_'.$creditLocale} ?: $creditGender->name_az) : null;
    $creditGender = $creditGender ? mb_convert_case(mb_substr($creditGender, 0, 1), MB_CASE_UPPER).mb_substr($creditGender, 1) : null;
    $creditType = $product->type ? preg_replace_callback('/\s(De|Du|La)\s/u', fn ($m) => ' '.mb_strtolower($m[1]).' ', $product->type->{'name_'.$creditLocale} ?: $product->type->name_az) : null;
    $creditSize = fn ($variant) => $variant->size?->{'name_'.$creditLocale} ?: ($variant->size?->name_az ?: $variant->id);
@endphp
<dialog id="creditApplicationDialog" class="credit-application-dialog" aria-labelledby="creditDialogTitle" data-error-message="{{ $creditCopy['error'] }}" data-success-message="{{ $creditCopy['success'] }}">
    <div class="credit-modal-head">
        <div>
            <h2 id="creditDialogTitle">{{ $creditCopy['title'] }}</h2>
            <p>{{ $creditCopy['subtitle'] }}</p>
        </div>
        <button type="button" class="credit-modal-close" data-credit-close aria-label="{{ $creditCopy['close'] }}">×</button>
    </div>
    <div class="credit-modal-layout">
        <form id="creditApplicationForm" action="{{ route('credit.application.store') }}" method="POST">
            @csrf
            <div class="credit-modal-field">
                <label>{{ $creditCopy['period'] }}</label>
                <div class="credit-modal-options" id="creditPeriodOptions">
                    @foreach($creditPeriods as $period)
                        <label class="credit-modal-pill">
                            <input type="radio" name="credit_period_id" value="{{ $period->id }}" data-months="{{ $period->month }}" data-rate="{{ $period->interest_rate }}" @checked($loop->first) required>
                            <span>{{ $period->month }} {{ $creditCopy['month'] }}{{ (float)$period->interest_rate === 0.0 ? ' · '.$creditCopy['free'] : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            {{-- Tək həcm varsa seçim göstərilmir (həcm kartda yazılır), çox həcm — yan-yana --}}
            <div class="credit-modal-field" @if($variants->count() < 2) hidden @endif>
                <label>{{ $creditCopy['size'] }}</label>
                <div class="credit-modal-options" id="creditVariantOptions">
                    @foreach($variants as $variant)
                        <label class="credit-modal-pill">
                            <input type="radio" name="product_variant_id" value="{{ $variant->id }}" data-price="{{ $variant->price }}" @checked($loop->first) required>
                            <span>{{ $creditSize($variant) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="credit-modal-summary">
                <div class="credit-modal-product">
                    @if($creditImage)
                        <img class="credit-modal-product__image" src="{{ asset('frontend/uploads/products/'.$creditImage->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">
                    @endif
                    <div class="credit-modal-product__body">
                        <strong>{{ $product->brand?->name }}</strong>
                        <span>{{ $product->name }}</span>
                        <ul class="credit-modal-product__specs">
                            @if($creditGender)
                                <li>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        @if($creditGenderKind === 'female')
                                            <circle cx="12" cy="9" r="5.5"/><path d="M12 14.5V22M8.5 18.5h7"/>
                                        @elseif($creditGenderKind === 'male')
                                            <circle cx="10" cy="14" r="5.5"/><path d="M14 10l6-6M15 4h5v5"/>
                                        @else
                                            <circle cx="12" cy="12" r="5"/><path d="M12 17v5M9.5 19.5h5M15.5 8.5L20 4M16.5 4H20v3.5"/>
                                        @endif
                                    </svg>
                                    <span>{{ $creditGender }}</span>
                                </li>
                            @endif
                            <li>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 2h4v3h-4zM9 5h6v2.5H9z"/><rect x="6" y="7.5" width="12" height="14" rx="2.5"/><path d="M9.5 12.5h5v5h-5z"/></svg>
                                <span id="creditSize">{{ $variants->first() ? $creditSize($variants->first()) : '—' }}</span>
                            </li>
                            @if($creditType)
                                <li>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5s6.5 7.2 6.5 12A6.5 6.5 0 0 1 5.5 14.5c0-4.8 6.5-12 6.5-12z"/><path d="M9 15a3 3 0 0 0 3 3"/></svg>
                                    <span>{{ $creditType }}</span>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
                <div><span>{{ $creditCopy['price'] }}</span><strong id="creditPrice">—</strong></div>
                <div><span>{{ $creditCopy['monthly'] }}</span><strong id="creditMonthly">—</strong></div>
                <div><span>{{ $creditCopy['total'] }}</span><strong id="creditTotal">—</strong></div>
            </div>
            <p id="creditApplicationMessage" class="credit-modal-message" role="status" aria-live="polite"></p>
            <button type="submit" class="btn btn-dark credit-modal-submit">{{ $creditCopy['submit'] }}</button>
        </form>
        <aside class="credit-modal-rules">
            <h3>{{ $creditCopy['rules'] }}</h3>
            <div class="credit-modal-rules-scroll">
                @forelse($creditTermItems as $item)
                    @php $rule = $item->{'content_'.$creditLocale} ?: $item->content_az; @endphp
                    @if($rule)
                        <p class="credit-rule-text"><strong>{{ $loop->iteration }}.</strong> {{ $rule }}</p>
                    @endif
                @empty
                    <p>—</p>
                @endforelse
            </div>
            <div class="credit-modal-scroll-hint" id="creditRulesScrollHint" aria-live="polite">
                <span>{{ $creditCopy['scrollHint'] }}</span><span aria-hidden="true">↓</span>
            </div>
            <label class="credit-modal-accept credit-modal-rules-accept">
                <input type="checkbox" name="accept_terms" form="creditApplicationForm" value="1" required>
                <span>{{ $creditCopy['accept'] }}</span>
            </label>
        </aside>
        <button type="submit" form="creditApplicationForm" class="btn btn-dark credit-modal-mobile-submit">{{ $creditCopy['submit'] }}</button>
    </div>
</dialog>
