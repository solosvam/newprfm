@php
    $creditLocale = app()->getLocale();
    $creditCopy = [
        'az' => ['title'=>'Hissə-hissə ödəniş üçün müraciət', 'subtitle'=>'Ölçünü və ödəniş müddətini seçin.', 'size'=>'Ətrin həcmi', 'period'=>'Kredit müddəti', 'price'=>'Qiymət', 'monthly'=>'Aylıq ödəniş', 'total'=>'Ümumi məbləğ', 'rules'=>'Onlayn hissə-hissə müraciət üçün qaydalar', 'accept'=>'Şərtlər və qaydalarla tanış oldum və qəbul edirəm', 'submit'=>'Müraciət et', 'close'=>'Bağla', 'success'=>'Müraciətiniz qəbul edildi.', 'error'=>'Müraciət göndərilmədi. Yenidən cəhd edin.', 'month'=>'ay'],
        'ru' => ['title'=>'Заявка на рассрочку', 'subtitle'=>'Выберите объём и срок оплаты.', 'size'=>'Объём', 'period'=>'Срок рассрочки', 'price'=>'Цена', 'monthly'=>'Ежемесячный платёж', 'total'=>'Общая сумма', 'rules'=>'Условия онлайн-рассрочки', 'accept'=>'Я ознакомился(-ась) и согласен(-на) с условиями', 'submit'=>'Отправить заявку', 'close'=>'Закрыть', 'success'=>'Ваша заявка принята.', 'error'=>'Не удалось отправить заявку.', 'month'=>'мес.'],
        'en' => ['title'=>'Installment application', 'subtitle'=>'Select a size and payment term.', 'size'=>'Fragrance size', 'period'=>'Payment term', 'price'=>'Price', 'monthly'=>'Monthly payment', 'total'=>'Total', 'rules'=>'Online installment terms and conditions', 'accept'=>'I have read and accept the terms and conditions', 'submit'=>'Submit application', 'close'=>'Close', 'success'=>'Your application has been received.', 'error'=>'Unable to submit your application.', 'month'=>'months'],
    ][$creditLocale] ?? null;
    $creditCopy ??= ['title'=>'Hissə-hissə ödəniş üçün müraciət'];
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
                <label for="creditVariant">{{ $creditCopy['size'] }}</label>
                <select id="creditVariant" name="product_variant_id" required>
                    @foreach($variants as $variant)
                        <option value="{{ $variant->id }}" data-price="{{ $variant->price }}">
                            {{ $variant->size?->{'name_'.$creditLocale} ?: ($variant->size?->name_az ?: $variant->id) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="credit-modal-field">
                <label for="creditPeriod">{{ $creditCopy['period'] }}</label>
                <select id="creditPeriod" name="credit_period_id" required>
                    @foreach($creditPeriods as $period)
                        <option value="{{ $period->id }}" data-months="{{ $period->month }}" data-rate="{{ $period->interest_rate }}">
                            {{ $period->month }} {{ $creditCopy['month'] }}{{ (float)$period->interest_rate === 0.0 ? ' · 0%' : ' · '.$period->interest_rate.'%' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="credit-modal-summary">
                <div><span>{{ $creditCopy['price'] }}</span><strong id="creditPrice">—</strong></div>
                <div><span>{{ $creditCopy['monthly'] }}</span><strong id="creditMonthly">—</strong></div>
                <div><span>{{ $creditCopy['total'] }}</span><strong id="creditTotal">—</strong></div>
            </div>
            <label class="credit-modal-accept">
                <input type="checkbox" name="accept_terms" value="1" required>
                <span>{{ $creditCopy['accept'] }}</span>
            </label>
            <p id="creditApplicationMessage" class="credit-modal-message" role="status" aria-live="polite"></p>
            <button type="submit" class="btn btn-dark credit-modal-submit">{{ $creditCopy['submit'] }}</button>
        </form>
        <aside class="credit-modal-rules">
            <h3>{{ $creditCopy['rules'] }}</h3>
            @forelse($creditTermItems as $item)
                @php
                    $title = $item->{'title_'.$creditLocale} ?: $item->title_az;
                    $content = $item->{'content_'.$creditLocale} ?: $item->content_az;
                @endphp
                @if($title || $content)
                    <details class="credit-rule" @if($loop->first) open @endif>
                        <summary>{{ $title ?: $creditCopy['rules'] }}</summary>
                        <div>{{ $content }}</div>
                    </details>
                @endif
            @empty
                <p>—</p>
            @endforelse
        </aside>
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
.credit-modal-field select {width:100%;padding:13px;border:1px solid #dcd5e6;border-radius:9px;background:var(--surface,#fff);color:inherit;font:inherit}
.credit-modal-summary {background:rgba(143,113,178,.09);border-radius:12px;padding:16px;margin:22px 0}
.credit-modal-summary div {display:flex;justify-content:space-between;gap:12px;padding:9px 0}.credit-modal-summary div+div {border-top:1px solid #ded6e6}
.credit-modal-accept {display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.5;cursor:pointer}.credit-modal-accept input {margin-top:4px}
.credit-modal-submit {width:100%;margin-top:20px}.credit-modal-message {font-size:14px;margin:14px 0 0}
.credit-modal-rules {background:rgba(143,113,178,.07);border-radius:12px;padding:20px;align-self:start;max-height:540px;overflow:auto}
.credit-modal-rules h3 {font-size:17px;margin:0 0 18px}.credit-rule {border-top:1px solid #ddd4e5;padding:13px 0}
.credit-rule summary {font-weight:600;cursor:pointer;line-height:1.45}.credit-rule div {padding-top:10px;line-height:1.65;white-space:pre-line;font-size:14px}
@media(max-width:720px){.credit-modal-layout{grid-template-columns:1fr;padding:20px}.credit-modal-head{padding:20px}.credit-modal-rules{max-height:none}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('creditApplicationDialog');
    const form = document.getElementById('creditApplicationForm');
    const variant = document.getElementById('creditVariant');
    const period = document.getElementById('creditPeriod');
    const message = document.getElementById('creditApplicationMessage');
    const submit = form.querySelector('[type=submit]');
    const currency = new Intl.NumberFormat(document.documentElement.lang || 'az', {minimumFractionDigits:2,maximumFractionDigits:2});
    const format = value => currency.format(value) + ' ₼';
    function calculate() {
        const price = Number(variant.selectedOptions[0]?.dataset.price || 0);
        const rate = Number(period.selectedOptions[0]?.dataset.rate || 0);
        const months = Number(period.selectedOptions[0]?.dataset.months || 1);
        const total = Math.round(price * (1 + rate / 100) * 100) / 100;
        document.getElementById('creditPrice').textContent = format(price);
        document.getElementById('creditMonthly').textContent = format(total / months);
        document.getElementById('creditTotal').textContent = format(total);
    }
    variant.addEventListener('change', calculate);
    period.addEventListener('change', calculate);
    document.querySelectorAll('[data-credit-apply]').forEach(button => button.addEventListener('click', function () {
        const selectedVariant = document.querySelector('[data-buybox] .size-pill.active-size-amount');
        const selectedPeriod = document.querySelector('[data-installment] input[name=installment]:checked');
        if (selectedVariant) variant.value = selectedVariant.dataset.variantId;
        if (selectedPeriod) {
            const option = [...period.options].find(option => Number(option.dataset.months) === Number(selectedPeriod.value));
            if (option) period.value = option.value;
        }
        message.textContent = '';
        calculate();
        dialog.showModal();
    }));
    dialog.querySelector('[data-credit-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        submit.disabled = true;
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
                throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || @json($creditCopy['error']));
            }
            message.style.color = '#168047';
            message.textContent = data.message || @json($creditCopy['success']);
            form.querySelector('[name=accept_terms]').checked = false;
        } catch (error) {
            message.style.color = '#b42318';
            message.textContent = error.message || @json($creditCopy['error']);
        } finally {submit.disabled = false;}
    });
});
</script>
