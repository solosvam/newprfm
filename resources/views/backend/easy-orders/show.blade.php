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
                @if($existing)
                    <div class="alert alert-info">Bu nömrə ilə müştəri tapıldı: {{ $existing->name }} {{ $existing->surname }} (ID: {{ $existing->id }}).</div>
                @else
                    <div class="alert alert-warning">Bu nömrə ilə müştəri tapılmadı. Təsdiqlədikdə yeni müştəri yaradılacaq.</div>
                @endif
                <form method="POST" action="{{ route('admin.easy-orders.confirm', $order) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Təsdiqlənmiş mobil nömrə</label>
                            <input class="form-control" name="mobile" required maxlength="12" pattern="994[0-9]{9}" value="{{ old('mobile', $order->guest_mobile) }}">
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
                        <div class="col-md-6">
                            <label class="form-label">Şəhər</label>
                            <input class="form-control" name="city" required maxlength="100" value="{{ old('city') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rayon</label>
                            <input class="form-control" name="district" maxlength="100" value="{{ old('district') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Çatdırılma ünvanı</label>
                            <textarea class="form-control" name="address" rows="2" maxlength="500" required>{{ old('address') }}</textarea>
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
