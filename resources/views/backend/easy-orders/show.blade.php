@php $title = 'Asan sifariş · ' . $order->order_no; $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.easy-orders.index') => 'Asan sifariş', '#' => $title]; @endphp
@extends('backend.layout', ['title' => $title])
@section('css')
{{-- Şəhər seçimi select2 ilə — onun CSS-i layout-da yoxdur (CRM müştəri səhifəsində olduğu kimi) --}}
<link rel="stylesheet" href="{{ asset('backend/css/vendor/select2.min.css') }}"/>
<link rel="stylesheet" href="{{ asset('backend/css/vendor/select2-bootstrap4.min.css') }}"/>
<style>
    /* Ünvan təklifləri (Google Places) — CRM ünvan pəncərəsindəki kimi */
    .address-suggest { position: absolute; left: 0; right: 0; top: 100%; z-index: 1060; margin: 4px 0 0; padding: 4px; list-style: none; background: var(--foreground); border: 1px solid var(--separator); border-radius: var(--border-radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
    .address-suggest[hidden] { display: none; }
    .address-suggest li { display: flex; flex-direction: column; padding: 7px 10px; border-radius: 6px; cursor: pointer; }
    .address-suggest li small { color: var(--muted); }
    .address-suggest li:hover, .address-suggest li.is-active { background: rgba(var(--primary-rgb), .08); }
</style>
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
                @php
                    // Ətir növü qısa: "Eau De Parfum" → EDP (tanınmırsa — adın özü)
                    $kindShort = fn (?string $type) => match (true) {
                        $type === null || $type === '' => null,
                        (bool) preg_match('/toilette/i', $type) => 'EDT',
                        (bool) preg_match('/cologne/i', $type) => 'EDC',
                        (bool) preg_match('/eau\s*de\s*parfum/i', $type) => 'EDP',
                        (bool) preg_match('/extrait/i', $type) => 'Extrait',
                        default => $type,
                    };
                @endphp
                @foreach($order->items as $item)
                    @php
                        $product = $item->product;
                        $image = $product?->images->first();
                        // Ada / şəklə klik — məhsulun saytdakı səhifəsi yeni tabda
                        $url = $product?->slug ? route('product', $product->slug) : null;
                    @endphp
                    <div class="d-flex align-items-center gap-3 border rounded p-2 mb-2">
                        <a @if($url) href="{{ $url }}" target="_blank" rel="noopener" @endif class="flex-shrink-0">
                            @if($image)
                                <img src="{{ asset('frontend/uploads/products/'.$image->image) }}" alt="{{ $product->name }}" width="56" height="56" class="rounded" style="object-fit: contain" loading="lazy">
                            @else
                                <span class="d-inline-block rounded bg-light" style="width: 56px; height: 56px"></span>
                            @endif
                        </a>
                        <div class="flex-grow-1 min-width-0">
                            @if($product?->brand)<div class="text-muted small">{{ html_entity_decode($product->brand->name) }}</div>@endif
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                @if($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="fw-bold body-link me-1">{{ $product->name }}</a>
                                @else
                                    <span class="fw-bold me-1">{{ $product?->name ?? 'Məhsul' }}</span>
                                @endif
                                @foreach($product?->genders ?? [] as $gender)<span class="badge {{ match (mb_strtolower($gender->name_az)) { 'qadın' => 'bg-outline-danger', 'kişi' => 'bg-outline-primary', default => 'bg-outline-muted' } }}">{{ $gender->name_az }}</span>@endforeach
                                @if($kind = $kindShort($product?->type?->name_az))<span class="badge bg-outline-info">{{ $kind }}</span>@endif
                                @if($item->variant?->size)<span class="badge bg-outline-muted">{{ $item->variant->size->name_az }}</span>@endif
                                <span class="text-muted small ms-1">×{{ $item->quantity }}</span>
                            </div>
                        </div>
                        <strong class="text-nowrap">{{ number_format((float)$item->total, 2) }} ₼</strong>
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
                                <option value="on_call" @selected(old('address_choice') === 'on_call')>📞 {{ \App\Models\Customer\CustomerAddress::ON_CALL_TEXT }}</option>
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
                                    <div class="position-relative"><input class="form-control" name="address" id="easyOrderAddress" maxlength="500" value="{{ old('address') }}" autocomplete="off"></div>
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
                                {{-- Lokasiya linki (WhatsApp) və xəritədə nöqtə — kuryer "Xəritədə aç" ilə dəqiq nöqtəyə gedir --}}
                                @if(config('services.google_maps.key'))
                                    <div class="col-12">
                                        @include('backend.crm.partials.address-map', ['id' => 'easyOrder', 'address' => '#easyOrderAddress', 'city' => '#easyOrderCity',
                                            'lat' => old('latitude'), 'lng' => old('longitude')])
                                    </div>
                                @endif
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
                        <div class="col-12">
                            @include('backend.crm.partials.courier-fee-field', ['id' => 'easyOrderCourierFee', 'value' => old('courier_fee', $order->courier_fee)])
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
                    <button class="btn btn-danger text-nowrap" type="submit">Sifarişi ləğv et</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
@section('js_page')
@if(config('services.google_maps.key'))<script src="{{ asset_v('shared/address-map.js') }}"></script>@endif
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
            select.replaceChildren(new Option('📞 {{ \App\Models\Customer\CustomerAddress::ON_CALL_TEXT }}', 'on_call'), new Option('+ Yeni ünvan əlavə et', 'new'));
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
            select.replaceChildren(new Option('📞 {{ \App\Models\Customer\CustomerAddress::ON_CALL_TEXT }}', 'on_call'));
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
            select.replaceChildren(new Option('📞 {{ \App\Models\Customer\CustomerAddress::ON_CALL_TEXT }}', 'on_call'), new Option('+ Yeni ünvan əlavə et', 'new'));
            select.value = 'new'; toggle();
        }
    });
});
</script>
@endsection
