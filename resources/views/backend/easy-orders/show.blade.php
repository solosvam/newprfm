@php $title = 'Asan sifariş · ' . $order->order_no; $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.easy-orders.index') => 'Asan sifariş', '#' => $title]; @endphp
@extends('backend.layout', ['title' => $title])
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
                <div class="d-flex justify-content-between mt-3"><strong>Ara cəm</strong><strong>{{ number_format((float)$order->subtotal, 2) }} ₼</strong></div>
                <p class="small text-muted mt-3">Müştəri ilə əlaqə saxlanılana qədər sifariş profilində görünmür.</p>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <h5>Müştəri ilə əlaqə və sifarişin tamamlanması</h5>
                <div id="customerFound" @if(!$existing) hidden @endif class="alert alert-info">Bu nömrə ilə müştəri tapıldı: {{ $existing?->name }} {{ $existing?->surname }} (ID: {{ $existing?->id }}).</div>
                <div id="customerNotFound" @if($existing) hidden @endif class="alert alert-warning">Bu nömrə ilə müştəri tapılmadı. Təsdiqlədikdə yeni müştəri yaradılacaq.</div>
                <form method="POST" action="{{ route('admin.easy-orders.confirm', $order) }}" data-lookup-url="{{ route('admin.easy-orders.lookup', $order) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Təsdiqlənmiş mobil nömrə</label>
                            <input class="form-control" name="mobile" id="easyOrderMobile" required maxlength="12" pattern="994[0-9]{9}" value="{{ old('mobile', $order->guest_mobile) }}">
                            <div class="form-text">Müştəri bu nömrə ilə axtarılır. Mövcud hesab varsa ona bağlanır.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ad</label>
                            <input class="form-control" name="name" required maxlength="30" value="{{ old('name', $existing?->name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Soyad</label>
                            <input class="form-control" name="surname" required maxlength="30" value="{{ old('surname', $existing?->surname) }}">
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
                                <div class="col-12">
                                    <label class="form-label">Ünvan adı (Ev, İş...)</label>
                                    <input class="form-control" name="title" maxlength="100" value="{{ old('title') }}" placeholder="Ev, İş...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Şəhər</label>
                                    <input class="form-control" name="city" maxlength="100" value="{{ old('city') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rayon</label>
                                    <input class="form-control" name="district" maxlength="100" value="{{ old('district') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Küçə və ünvan</label>
                                    <input class="form-control" name="address" maxlength="500" value="{{ old('address') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bina</label>
                                    <input class="form-control" name="building" maxlength="50" value="{{ old('building') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Blok</label>
                                    <input class="form-control" name="entrance" maxlength="30" value="{{ old('entrance') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mərtəbə</label>
                                    <input class="form-control" name="floor" maxlength="30" value="{{ old('floor') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mənzil</label>
                                    <input class="form-control" name="apartment" maxlength="30" value="{{ old('apartment') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ünvan qeydi</label>
                                    <textarea class="form-control" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ödəniş üsulu</label>
                            <select class="form-select" name="payment_method_id" required>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected(old('payment_method_id', $order->payment_method_id) == $method->id)>{{ $method->localized_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger">{{ $errors->first() }}</div></div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary" type="submit">Müştəriyə bağla və sifarişi təsdiqlə</button></div>
                    </div>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
@section('js_page')
<script>
document.addEventListener('DOMContentLoaded', () => {
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
        newAddress.querySelectorAll('input[name="title"], input[name="city"], input[name="address"]')
            .forEach(el => { el.required = isNew; });
    };
    select.addEventListener('change', toggle);
    toggle();
    let seq = 0;
    mobile.addEventListener('input', async () => {
        const current = ++seq;
        const value = mobile.value.replace(/\D/g, '');
        if (!/^994\d{9}$/.test(value)) {
            select.replaceChildren(new Option('+ Yeni ünvan əlavə et', 'new'));
            select.value = 'new'; toggle();
            if (found) found.hidden = true;
            if (notFound) notFound.hidden = true;
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
            if (result.found) {
                name.value = result.name || '';
                surname.value = result.surname || '';
            } else {
                name.value = '';
                surname.value = '';
            }
            genderField.hidden = !!result.found;
            gender.required = !result.found;
            if (found) {
                found.hidden = !result.found;
                found.textContent = result.found
                    ? 'Bu nömrə ilə müştəri tapıldı: ' + result.name + ' ' + result.surname + ' (ID: ' + result.customer_id + ').'
                    : '';
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
