@php
    $creditLocale = app()->getLocale();
    $creditCopy = [
        'az' => ['title'=>'Hissə-hissə ödəniş üçün müraciət', 'subtitle'=>'Ölçünü və ödəniş müddətini seçin.', 'size'=>'Ətrin həcmi', 'period'=>'Kredit müddəti', 'price'=>'Qiymət', 'monthly'=>'Aylıq ödəniş', 'total'=>'Ümumi məbləğ', 'rules'=>'Onlayn hissə-hissə müraciət üçün qaydalar', 'accept'=>'Şərtlər və qaydalarla tanış oldum və qəbul edirəm', 'submit'=>'Müraciət et', 'close'=>'Bağla', 'success'=>'Müraciətiniz qəbul edildi.', 'error'=>'Müraciət göndərilmədi. Yenidən cəhd edin.', 'month'=>'ay', 'free'=>'Faizsiz', 'scrollHint'=>'Bütün qaydaları oxumaq üçün aşağı sürüşdürün'],
        'ru' => ['title'=>'Заявка на рассрочку', 'subtitle'=>'Выберите объём и срок оплаты.', 'size'=>'Объём', 'period'=>'Срок рассрочки', 'price'=>'Цена', 'monthly'=>'Ежемесячный платёж', 'total'=>'Общая сумма', 'rules'=>'Условия онлайн-рассрочки', 'accept'=>'Я ознакомился(-ась) и согласен(-на) с условиями', 'submit'=>'Отправить заявку', 'close'=>'Закрыть', 'success'=>'Ваша заявка принята.', 'error'=>'Не удалось отправить заявку.', 'month'=>'мес.', 'free'=>'Без процентов', 'scrollHint'=>'Прокрутите вниз, чтобы прочитать все условия'],
        'en' => ['title'=>'Installment application', 'subtitle'=>'Select a size and payment term.', 'size'=>'Fragrance size', 'period'=>'Payment term', 'price'=>'Price', 'monthly'=>'Monthly payment', 'total'=>'Total', 'rules'=>'Online installment terms and conditions', 'accept'=>'I have read and accept the terms and conditions', 'submit'=>'Submit application', 'close'=>'Close', 'success'=>'Your application has been received.', 'error'=>'Unable to submit your application.', 'month'=>'months', 'free'=>'Interest-free', 'scrollHint'=>'Scroll down to read all the terms'],
    ][$creditLocale] ?? null;
    $creditErrorMessage = $creditCopy['error'];
    $creditSuccessMessage = $creditCopy['success'];
@endphp

<dialog id="creditApplicationDialog" class="credit-application-dialog" aria-labelledby="creditDialogTitle">
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

<style>
.credit-application-dialog {width:min(1040px,calc(100vw - 32px));max-height:calc(100dvh - 36px);padding:0;border:0;border-radius:18px;background:var(--surface,#fff);color:var(--text,#272331);box-shadow:0 20px 80px #0003;overflow-y:auto}
.credit-application-dialog::backdrop {background:rgba(17,12,29,.68)}
.credit-modal-head {display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:26px 30px;border-bottom:1px solid #eae6f0}
.credit-modal-head h2 {font-size:24px;margin:0 0 7px}.credit-modal-head p {margin:0;color:#797483}
.credit-modal-close {background:transparent;border:0;font-size:32px;line-height:1;cursor:pointer;color:inherit}
.credit-modal-layout {display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:26px;padding:28px 30px}
.credit-modal-field {margin-bottom:20px}.credit-modal-field label {display:block;font-weight:600;margin-bottom:9px}
.credit-modal-options{display:flex;flex-wrap:wrap;gap:10px}
.credit-modal-pill{position:relative;cursor:pointer;margin:0!important}
.credit-modal-pill input{position:absolute;opacity:0;width:1px;height:1px}
.credit-modal-pill span{display:block;border:1px solid #dcd5e6;border-radius:10px;padding:12px 17px;background:var(--surface,#fff);color:inherit;transition:background .15s,border-color .15s}
.credit-modal-pill input:checked+span{border-color:#30214f;background:#f2edf9;color:#30214f;font-weight:700}
.credit-modal-pill input:focus-visible+span{outline:2px solid #30214f;outline-offset:3px}
.credit-modal-summary {background:rgba(143,113,178,.09);border-radius:12px;padding:16px;margin:22px 0}
.credit-modal-summary .credit-modal-product{display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:2px 0 14px;margin-bottom:5px;border-bottom:0}
.credit-modal-summary .credit-modal-product strong{font-size:13px;color:#766b86}
.credit-modal-summary .credit-modal-product span{font-size:18px;font-weight:700;line-height:1.35}
.credit-modal-summary div {display:flex;justify-content:space-between;gap:12px;padding:9px 0}.credit-modal-summary > div:not(.credit-modal-product) + div:not(.credit-modal-product) {border-top:1px solid #ded6e6}
.credit-modal-accept {display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.5;cursor:pointer}.credit-modal-accept input {margin-top:4px}
.credit-modal-submit {width:100%;margin-top:20px}.credit-modal-mobile-submit{display:none}.credit-modal-message {font-size:14px;margin:14px 0 0}
.credit-modal-rules {background:rgba(143,113,178,.07);border-radius:12px;padding:20px;align-self:start;height:min(540px,calc(100dvh - 230px));min-height:260px;display:flex;flex-direction:column;overflow:hidden}
.credit-modal-rules-scroll{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding-right:6px;scrollbar-width:thin;scrollbar-color:#8771a5 #e9e2f1}
.credit-modal-scroll-hint{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-shrink:0;font-size:12px;font-weight:600;color:#624b83;padding:10px 4px 0}
.credit-modal-scroll-hint span:last-child{font-size:22px;line-height:1}
.credit-modal-scroll-hint[hidden]{display:none}
.credit-modal-rules-accept{flex-shrink:0;border-top:1px solid #ddd4e5;padding-top:16px;margin-top:12px}
.credit-modal-rules h3 {font-size:17px;margin:0 0 18px}.credit-rule-text{border-top:1px solid #ddd4e5;padding:13px 0;margin:0;line-height:1.65;white-space:pre-line;font-size:14px}
@media(max-width:720px){
.credit-application-dialog{position:fixed;inset:12px;margin:auto;width:calc(100vw - 24px);max-width:480px;max-height:calc(100dvh - 24px);height:calc(100dvh - 24px);display:none;flex-direction:column;overflow:hidden}
.credit-application-dialog[open]{display:flex}
.credit-modal-head{flex-shrink:0;padding:18px 20px}
.credit-modal-layout{display:flex;flex-direction:column;gap:16px;padding:20px;flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
.credit-modal-layout form{flex-shrink:0}
.credit-modal-submit{display:none}
.credit-modal-mobile-submit{display:block;width:100%;flex-shrink:0;margin:0 0 4px}
.credit-modal-rules{width:100%;height:340px;min-height:280px;flex-shrink:0;align-self:stretch;padding:16px}
.credit-modal-rules h3{flex-shrink:0;margin-bottom:12px}
.credit-modal-rules-accept{font-size:13px;padding-top:12px;margin-top:8px}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('creditApplicationDialog');
    const form = document.getElementById('creditApplicationForm');
    const variantOptions = document.getElementById('creditVariantOptions');
    const periodOptions = document.getElementById('creditPeriodOptions');
    const message = document.getElementById('creditApplicationMessage');
    const submitButtons = [form.querySelector('[type=submit]'), dialog.querySelector('.credit-modal-mobile-submit')];
    const rulesScroll = dialog.querySelector('.credit-modal-rules-scroll');
    const rulesHint = document.getElementById('creditRulesScrollHint');
    const acceptTerms = dialog.querySelector('[name=accept_terms]');
    const rulesAtBottom = () => rulesScroll.scrollTop + rulesScroll.clientHeight >= rulesScroll.scrollHeight - 5;
    function updateRulesHint() {
        rulesHint.hidden = rulesScroll.scrollHeight <= rulesScroll.clientHeight + 5 || rulesAtBottom();
    }
    rulesScroll.addEventListener('scroll', updateRulesHint, {passive:true});
    acceptTerms.addEventListener('click', function (event) {
        if (!rulesAtBottom()) {
            event.preventDefault();
            acceptTerms.checked = false;
            rulesScroll.scrollTo({top:rulesScroll.scrollHeight,behavior:'smooth'});
        }
    });
    const currency = new Intl.NumberFormat(document.documentElement.lang || 'az', {minimumFractionDigits:2,maximumFractionDigits:2});
    const format = value => currency.format(value) + ' ₼';
    function calculate() {
        const price = Number(variantOptions.querySelector('input:checked')?.dataset.price || 0);
        const rate = Number(periodOptions.querySelector('input:checked')?.dataset.rate || 0);
        const months = Number(periodOptions.querySelector('input:checked')?.dataset.months || 1);
        const total = Math.round(price * (1 + rate / 100) * 100) / 100;
        document.getElementById('creditPrice').textContent = format(price);
        document.getElementById('creditMonthly').textContent = format(total / months);
        document.getElementById('creditTotal').textContent = format(total);
    }
    variantOptions.addEventListener('change', calculate);
    periodOptions.addEventListener('change', calculate);
    document.querySelectorAll('[data-credit-apply]').forEach(button => button.addEventListener('click', function () {
        const selectedVariant = document.querySelector('[data-buybox] .size-pill.active-size-amount');
        const selectedPeriod = document.querySelector('[data-installment] input[name=installment]:checked');
        if (selectedVariant) { const radio = variantOptions.querySelector('input[value="' + selectedVariant.dataset.variantId + '"]'); if (radio) radio.checked = true; }
        if (selectedPeriod) {
            const radio = [...periodOptions.querySelectorAll('input')].find(input => Number(input.dataset.months) === Number(selectedPeriod.value));
            if (radio) radio.checked = true;
        }
        message.textContent = '';
        calculate();
        rulesScroll.scrollTop = 0;
        acceptTerms.checked = false;
        dialog.showModal();
        updateRulesHint();
    }));
    dialog.querySelector('[data-credit-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        submitButtons.forEach(button => { button.disabled = true; });
        message.textContent = '';
        try {
            const response = await fetch(form.action, {
                method:'POST',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},
                body:new FormData(form),
                credentials:'same-origin'
            });
            const data = await response.json();
            if (!response.ok) {
                if (data.redirect) {window.location.href = data.redirect;return;}
                throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || @json($creditErrorMessage));
            }
            message.style.color = '#168047';
            message.textContent = data.message || @json($creditSuccessMessage);
            form.querySelector('[name=accept_terms]').checked = false;
        } catch (error) {
            message.style.color = '#b42318';
            message.textContent = error.message || @json($creditErrorMessage);
        } finally {submitButtons.forEach(button => { button.disabled = false; });}
    });
});
</script>
