@php $editing = $promo->exists; $title = $editing ? 'Promo kodu düzəlt' : 'Yeni promo kod'; @endphp
@extends('backend.layout', ['title' => $title])
@section('content')
<div class="container">
    <div class="page-title-container mb-4"><h1 class="mb-0 pb-0 display-4">{{ $title }}</h1></div>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ $editing ? route('admin.promo-codes.update',$promo) : route('admin.promo-codes.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Kod</label>
                    <input class="form-control text-uppercase @error('code') is-invalid @enderror" name="code" maxlength="80" required value="{{ old('code',$promo->code) }}" placeholder="WELCOME10">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Endirim növü</label>
                    <select class="form-select" name="type" required>
                        <option value="percent" @selected(old('type',$promo->type)==='percent')>Faiz (%)</option>
                        <option value="fixed" @selected(old('type',$promo->type)==='fixed')>Sabit (₼)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Endirim dəyəri</label>
                    <input class="form-control @error('value') is-invalid @enderror" name="value" type="number" min="0.01" step="0.01" required value="{{ old('value',$promo->value) }}">
                    @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4"><label class="form-label">Minimum sifariş (₼)</label><input class="form-control" name="min_amount" type="number" min="0" step="0.01" value="{{ old('min_amount',$promo->min_amount) }}"></div>
                <div class="col-md-4"><label class="form-label">Maksimum endirim (₼)</label><input class="form-control" name="max_discount" type="number" min="0.01" step="0.01" value="{{ old('max_discount',$promo->max_discount) }}"></div>
                <div class="col-md-4"><label class="form-label">İstifadə limiti (boş = limitsiz)</label><input class="form-control" name="usage_limit" type="number" min="{{ max(1,(int)$promo->used_count) }}" value="{{ old('usage_limit',$promo->usage_limit) }}"></div>
                <div class="col-md-6"><label class="form-label">Başlama tarixi</label><input class="form-control" name="starts_at" type="datetime-local" value="{{ old('starts_at',$promo->starts_at?->format('Y-m-d\\TH:i')) }}"></div>
                <div class="col-md-6"><label class="form-label">Bitmə tarixi</label><input class="form-control @error('expires_at') is-invalid @enderror" name="expires_at" type="datetime-local" value="{{ old('expires_at',$promo->expires_at?->format('Y-m-d\\TH:i')) }}">@error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="active" name="is_active" value="1" @checked(old('is_active',$editing ? $promo->is_active : true))><label class="form-check-label" for="active">Aktiv</label></div></div>
            </div>
            <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Yadda saxla</button><a class="btn btn-outline-secondary" href="{{ route('admin.promo-codes.index') }}">Geri</a></div>
        </form>
    </div></div>
</div>
@endsection
