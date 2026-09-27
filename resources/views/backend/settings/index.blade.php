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
                <h5 class="mb-3">Çatdırılma</h5>
                <label for="delivery_mode" class="form-label">Çatdırılma qaydası</label>
                <select id="delivery_mode" name="delivery_mode" class="form-select mb-3">
                    <option value="free" @selected(old('delivery_mode',$deliveryMode)==='free')>Tam pulsuz</option>
                    <option value="threshold" @selected(old('delivery_mode',$deliveryMode)==='threshold')>Müəyyən məbləğdən yuxarı pulsuz</option>
                </select>
                <div id="deliveryThreshold" @if(old('delivery_mode',$deliveryMode)!=='threshold') style="display:none" @endif>
                    <label for="delivery_fee" class="form-label">Çatdırılma haqqı (AZN)</label>
                    <input id="delivery_fee" name="delivery_fee" type="number" min="0" step="0.01" class="form-control mb-3" value="{{ old('delivery_fee',$deliveryFee) }}">
                    <label for="free_delivery_from" class="form-label">Pulsuz çatdırılma üçün minimum məbləğ (AZN)</label>
                    <input id="free_delivery_from" name="free_delivery_from" type="number" min="0.01" step="0.01" class="form-control" value="{{ old('free_delivery_from',$freeDeliveryFrom) }}">
                </div>
                @error('delivery_mode')<p class="text-danger">{{ $message }}</p>@enderror
                @error('delivery_fee')<p class="text-danger">{{ $message }}</p>@enderror
                @error('free_delivery_from')<p class="text-danger">{{ $message }}</p>@enderror
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
<script>document.getElementById('delivery_mode').addEventListener('change',function(){document.getElementById('deliveryThreshold').style.display=this.value==='threshold'?'':'none';});</script>
@endsection
