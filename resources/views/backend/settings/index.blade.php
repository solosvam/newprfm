@php $title = 'Ayarlar'; @endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container mb-4">
        <h1 class="mb-0 pb-0 display-4">Ayarlar</h1>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Sifariş ayarları</h5>
                <label for="order_bonus_percent" class="form-label">Sifariş bonusu (%)</label>
                <input id="order_bonus_percent" type="number" step="0.01" min="0" max="100"
                       name="order_bonus_percent"
                       value="{{ old('order_bonus_percent', $bonusPercent) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('order_bonus_percent')]) required>
                <div class="form-text">Sifariş məbləğinin bonus kimi qaytarılacaq faizi.</div>
                @error('order_bonus_percent')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-2">Banner ölçüləri</h5>
                <p class="text-muted mb-4">Bütün ölçülər piksellə (px) göstərilir. Yeni yüklənən bannerlər bu ölçülərə uyğun kəsiləcək.</p>

                @foreach ([
                    'banner_web_top' => 'Veb — yuxarı',
                    'banner_web_bottom' => 'Veb — aşağı',
                    'banner_mobile_top' => 'Mobil — yuxarı',
                    'banner_mobile_bottom' => 'Mobil — aşağı',
                ] as $key => $label)
                    <div class="mb-4">
                        <h6 class="mb-3">{{ $label }}</h6>
                        <div class="row g-3">
                            @foreach (['width' => 'En (px)', 'height' => 'Hündürlük (px)'] as $dimension => $caption)
                                @php $field = "{$key}_{$dimension}"; @endphp
                                <div class="col-md-6">
                                    <label for="{{ $field }}" class="form-label">{{ $caption }}</label>
                                    <input id="{{ $field }}" type="number" name="{{ $field }}"
                                           min="1" max="10000" step="1"
                                           value="{{ old($field, $bannerSizes[$key][$dimension]) }}"
                                           @class(['form-control', 'is-invalid' => $errors->has($field)]) required>
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Yadda saxla</button>
    </form>
</div>
@endsection
