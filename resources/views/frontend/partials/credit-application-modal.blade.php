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
            <div class="credit-modal-field">
                <label>{{ $creditCopy['size'] }}</label>
                <div class="credit-modal-options" id="creditVariantOptions">
                    @foreach($variants as $variant)
                        <label class="credit-modal-pill">
                            <input type="radio" name="product_variant_id" value="{{ $variant->id }}" data-price="{{ $variant->price }}" @checked($loop->first) required>
                            <span>{{ $variant->size?->{'name_'.$creditLocale} ?: ($variant->size?->name_az ?: $variant->id) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="credit-modal-summary">
                <div class="credit-modal-product"><strong>{{ $product->brand?->name }}</strong><span>{{ $product->name }}</span></div>
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
