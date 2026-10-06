@php $title = 'Asan sifariş · ' . $order->order_no; $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.easy-orders.index') => 'Asan sifariş', '#' => $title]; @endphp
@extends('backend.layout', ['title' => $title])
@section('css')
<style>
    /* Tapılmış müştərinin nömrə, ad, soyadı dəyişdirilmir: disabled kimi görünsün (readonly — formla göndərilir) */
    form[data-lookup-url] .form-control[readonly] { background: var(--background); color: var(--muted); cursor: not-allowed; }
</style>
@endsection
@section('content')
<div class="container">
    <h1 class="display-4 mb-4">{{ $title }}</h1>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <h5>Məhsullar</h5>
                @foreach($order->items as $item)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $item->product?->name ?? 'Məhsul' }} · {{ $item->variant?->size?->name_az }} ×{{ $item->quantity }}</span>
                        <strong>{{ number_format((float)$item->total, 2) }} ₼</strong>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between mt-3"><strong>Məhsullar</strong><strong>{{ number_format((float)$order->subtotal, 2) }} ₼</strong></div>
                <p class="small text-muted mt-3">Müştəri ilə əlaqə saxlanılana qədər sifariş profilində görünmür.</p>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <h5>Müştəri ilə əlaqə və sifarişin tamamlanması</h5>
                <div id="customerFound" @if(!$existing) hidden @endif
                     class="alert alert-info d-flex align-items-center justify-content-between gap-3"
                     data-profile-url="{{ route('admin.crm.customer', ':id') }}">
                    <span>Bu nömrə ilə müştəri tapıldı: <strong data-customer-name>{{ $existing?->name }} {{ $existing?->surname }}</strong></span>
                    <a class="btn btn-sm btn-outline-primary text-nowrap" data-customer-profile target="_blank" rel="noopener"
                       href="{{ $existing ? route('admin.crm.customer', $existing->id) : '#' }}">Profilinə bax</a>
                </div>
                <div id="customerNotFound" @if($existing) hidden @endif class="alert alert-warning">Bu nömrə ilə müştəri tapılmadı. Təsdiqlədikdə yeni müştəri yaradılacaq.</div>
                <form method="POST" action="{{ route('admin.easy-orders.confirm', $order) }}" data-lookup-url="{{ route('admin.easy-orders.lookup', $order) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Təsdiqlənmiş mobil nömrə</label>
                            <input class="form-control" name="mobile" id="easyOrderMobile" required maxlength="12" pattern="994[0-9]{9}" value="{{ old('mobile', $order->guest_mobile) }}" @readonly($existing)>
                            <div class="form-text">Müştəri bu nömrə ilə axtarılır. Mövcud hesab varsa ona bağlanır.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ad</label>
                            <input class="form-control" name="name" required maxlength="30" value="{{ $existing?->name ?? old('name') }}" @readonly($existing)>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Soyad</label>
                            <input class="form-control" name="surname" required maxlength="30" value="{{ $existing?->surname ?? old('surname') }}" @readonly($existing)>
                        </div>
                        <div class="col-md-6" id="easyOrderGenderField" @if($existing) hidden @endif>
                            <label for="easyOrderGender" class="form-label">Cinsiyyət (yeni müştəri üçün)</label>
                            <select class="form-select" id="easyOrderGender" name="gender" @if(!$existing) required @endif>
                                <option value="">Seçin</option>
                                <option value="1" @selected(old('gender') === '1')>Kişi</option>
                                <option value="0" @selected(old('gender') === '0')>Qadın</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="easyOrderAddressChoice" class="form-label">Çatdırılma ünvanı</label>
                            <select class="form-select" name="address_choice" id="easyOrderAddressChoice" required>
                                @foreach($addresses as $a)
                                    <option value="{{ $a->id }}" @selected(old('address_choice', $addresses->first()?->id) == $a->id)>{{ $a->label }}</option>
                                @endforeach
                                <option value="new" @selected(old('address_choice', $addresses->isEmpty() ? 'new' : '') === 'new')>+ Yeni ünvan əlavə et</option>
                            </select>
                        </div>
                        <div id="easyOrderNewAddress" class="col-12" @if($addresses->isNotEmpty() && old('address_choice', $addresses->first()?->id) !== 'new') hidden @endif>
                            <div class="row g-3">
                                {{-- Ünvan adını operator vermir: controller "Ünvan #N" yazır --}}
                                <div class="col-12">
                                    <label class="form-label" for="easyOrderCity">Şəhər *</label>
                                    <select class="form-select" name="city_id" id="easyOrderCity" data-placeholder="Şəhər seçin">
                                        <option value=""></option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Küçə və ünvan *</label>
                                    <input class="form-control" name="address" maxlength="500" value="{{ old('address') }}">
                                </div>
                                @php $hasDetails = old('building') || old('entrance') || old('floor') || old('apartment'); @endphp
                                <div class="col-12">
                                    <button type="button" class="btn btn-link p-0" id="easyOrderDetailsToggle" aria-expanded="{{ $hasDetails ? 'true' : 'false' }}">
                                        + Ətraflı: bina, blok, mərtəbə, mənzil
                                    </button>
                                </div>
                                <div class="col-12" id="easyOrderAddressDetails" @unless($hasDetails) hidden @endunless>
                                    <div class="row g-3">
                                        <div class="col-6 col-md-3"><input class="form-control" name="building" maxlength="50" value="{{ old('building') }}" placeholder="Bina"></div>
                                        <div class="col-6 col-md-3"><input class="form-control" name="entrance" maxlength="50" value="{{ old('entrance') }}" placeholder="Blok"></div>
                                        <div class="col-6 col-md-3"><input class="form-control" name="floor" maxlength="30" value="{{ old('floor') }}" placeholder="Mərtəbə"></div>
                                        <div class="col-6 col-md-3"><input class="form-control" name="apartment" maxlength="30" value="{{ old('apartment') }}" placeholder="Mənzil"></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Əlavə məlumat</label>
                                    <textarea class="form-control" name="address_note" rows="2" maxlength="1000" placeholder="Kuryer üçün qeyd, orientir...">{{ old('address_note') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ödəniş üsulu</label>
                            <select class="form-select" name="payment_method_id" id="easyOrderPayment" required>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" data-code="{{ $method->code }}" @selected(old('payment_method_id', $order->payment_method_id) == $method->id)>{{ $method->localized_name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="easyOrderPaymentHint" hidden>Sifariş "ödəniş gözləyir" statusunda yaranacaq. Müştəri ödənişi saytda "Sifarişlərim" bölməsindən edəcək.</div>
                        </div>
                        <div class="col-12" id="easyOrderBirbankMonths" hidden>
                            <label class="form-label">Birbank taksit müddəti</label>
                            <div class="d-flex gap-2">
                                @foreach([2, 3, 6] as $months)
                                    <input type="radio" class="btn-check" name="birbank_installment_months" id="easyBirbank{{ $months }}" value="{{ $months }}" @checked(old('birbank_installment_months') == $months)>
                                    <label class="btn btn-outline-primary" for="easyBirbank{{ $months }}">{{ $months }} ay</label>
                                @endforeach
                            </div>
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger">{{ $errors->first() }}</div></div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary" type="submit">Müştəriyə bağla və sifarişi təsdiqlə</button></div>
                    </div>
                </form>

                <hr class="my-4">
                <form method="POST" action="{{ route('admin.easy-orders.destroy', $order) }}" id="easyOrderDestroy"
                      class="d-flex align-items-center justify-content-between gap-3"
                      data-confirm="{{ $order->order_no }} nömrəli sifariş ləğv ediləcək və bütün məlumatları sistemdən silinəcək. Bu əməliyyat geri qaytarılmır. Davam edilsin?">
                    @csrf
                    @method('DELETE')
                    <span class="text-muted small">Müştəri sifarişdən imtina edibsə və ya sifariş səhvdirsə.</span>
                    <button class="btn btn-outline-danger text-nowrap" type="submit">Sifarişi ləğv et</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
@section('js_page')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Ləğv: təsdiq soruş
    document.getElementById('easyOrderDestroy')?.addEventListener('submit', event => {
        if (!window.confirm(event.currentTarget.dataset.confirm)) event.preventDefault();
    });

    const form = document.querySelector('form[data-lookup-url]');
    if (!form) return;
    const mobile = document.getElementById('easyOrderMobile');
    const select = document.getElementById('easyOrderAddressChoice');
    const newAddress = document.getElementById('easyOrderNewAddress');
    const found = document.getElementById('customerFound');
    const notFound = document.getElementById('customerNotFound');
    const name = form.querySelector('[name="name"]');
    const surname = form.querySelector('[name="surname"]');
    const genderField = document.getElementById('easyOrderGenderField');
    const gender = document.getElementById('easyOrderGender');
    const toggle = () => {
        const isNew = select.value === 'new';
        newAddress.hidden = !isNew;
        // Şəhər select2 ilə gizlədildiyi üçün "required" yalnız serverdə yoxlanır
        newAddress.querySelectorAll('input[name="address"]')
            .forEach(el => { el.required = isNew; });
    };
    select.addEventListener('change', toggle);
    toggle();

    // Şəhər: select2 (Acorn bootstrap4 teması)
    if (window.jQuery?.fn?.select2) {
        const city = jQuery('#easyOrderCity');
        city.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: city.data('placeholder'),
            language: { noResults: () => 'Şəhər tapılmadı' },
        });
    }
    // Ödəniş üsulu: Birbank → ay seçimi; kart / Birbank → "müştəri profildən ödəyəcək" qeydi
    const payment = document.getElementById('easyOrderPayment');
    const birbankMonths = document.getElementById('easyOrderBirbankMonths');
    const paymentHint = document.getElementById('easyOrderPaymentHint');
    const syncPayment = () => {
        const code = payment.selectedOptions[0]?.dataset.code;
        birbankMonths.hidden = code !== 'birbank_installment';
        birbankMonths.querySelectorAll('input').forEach(i => { i.required = code === 'birbank_installment'; });
        paymentHint.hidden = !['card_online', 'birbank_installment'].includes(code);
    };
    payment.addEventListener('change', syncPayment);
    syncPayment();

    const detailsToggle = document.getElementById('easyOrderDetailsToggle');
    const details = document.getElementById('easyOrderAddressDetails');
    detailsToggle.addEventListener('click', () => {
        details.hidden = !details.hidden;
        detailsToggle.setAttribute('aria-expanded', String(!details.hidden));
    });
    let seq = 0;
    mobile.addEventListener('input', async () => {
        const current = ++seq;
        const value = mobile.value.replace(/\D/g, '');
        if (!/^994\d{9}$/.test(value)) {
            select.replaceChildren(new Option('+ Yeni ünvan əlavə et', 'new'));
            select.value = 'new'; toggle();
            if (found) found.hidden = true;
            if (notFound) notFound.hidden = true;
            name.readOnly = surname.readOnly = false;
            genderField.hidden = false;
            gender.required = true;
            return;
        }
        try {
            const response = await fetch(form.dataset.lookupUrl + '?mobile=' + encodeURIComponent(value), {
                headers: {'Accept': 'application/json'}
            });
            if (!response.ok) throw new Error('Axtarış alınmadı');
            const result = await response.json();
            if (current !== seq) return;
            select.replaceChildren();
            (result.addresses || []).forEach(a => select.add(new Option(a.label, a.id)));
            select.add(new Option('+ Yeni ünvan əlavə et', 'new'));
            select.value = result.addresses?.length ? String(result.addresses[0].id) : 'new';
            // Tapılmış müştərinin adı dəyişdirilə bilməz (server də mövcud müştərini yeniləmir)
            name.value = result.found ? (result.name || '') : '';
            surname.value = result.found ? (result.surname || '') : '';
            name.readOnly = surname.readOnly = !!result.found;
            genderField.hidden = !!result.found;
            gender.required = !result.found;
            if (found) {
                found.hidden = !result.found;
                if (result.found) {
                    found.querySelector('[data-customer-name]').textContent = (result.name || '') + ' ' + (result.surname || '');
                    found.querySelector('[data-customer-profile]').href = found.dataset.profileUrl.replace(':id', result.customer_id);
                }
            }
            if (notFound) notFound.hidden = !!result.found;
            toggle();
        } catch (e) {
            if (current !== seq) return;
            select.replaceChildren(new Option('+ Yeni ünvan əlavə et', 'new'));
            select.value = 'new'; toggle();
        }
    });
});
</script>
@endsection
