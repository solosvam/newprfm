@php $title = 'Ayarlar'; @endphp
@extends('backend.layout', ['title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container d-flex align-items-center justify-content-between mb-4">
            <h1 class="mb-0 pb-0 display-4">Ayarlar</h1>
            <button type="submit" form="settingsForm" class="btn btn-primary">Yadda saxla</button>
        </div>

        <form id="settingsForm" method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div class="row g-4 align-items-start">
                {{-- Sifariş və çatdırılma --}}
                <div class="col-xl-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="mb-3">Sifariş və çatdırılma</h5>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="order_bonus_percent" class="form-label">Sifariş bonusu</label>
                                    <div class="input-group has-validation">
                                        <input id="order_bonus_percent" type="number" step="0.01" min="0" max="100"
                                               name="order_bonus_percent"
                                               value="{{ old('order_bonus_percent', $bonusPercent) }}"
                                               @class(['form-control', 'is-invalid' => $errors->has('order_bonus_percent')]) required>
                                        <span class="input-group-text">%</span>
                                        @error('order_bonus_percent')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="delivery_mode" class="form-label">Çatdırılma qaydası</label>
                                    <select id="delivery_mode" name="delivery_mode"
                                        @class(['form-select', 'is-invalid' => $errors->has('delivery_mode')])>
                                        <option value="free" @selected(old('delivery_mode', $deliveryMode) === 'free')>Tam pulsuz</option>
                                        <option value="paid" @selected(old('delivery_mode', $deliveryMode) === 'paid')>Pullu çatdırılma</option>
                                        <option value="threshold" @selected(old('delivery_mode', $deliveryMode) === 'threshold')>Məbləğdən yuxarı pulsuz</option>
                                    </select>
                                    @error('delivery_mode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div id="deliveryFields"
                                @class(['row g-3 mt-0', 'd-none' => old('delivery_mode', $deliveryMode) === 'free'])>
                                <div class="col-sm-6">
                                    <label for="delivery_fee" class="form-label">Çatdırılma haqqı</label>
                                    <div class="input-group has-validation">
                                        <input id="delivery_fee" name="delivery_fee" type="number" min="0" step="0.01"
                                               value="{{ old('delivery_fee', $deliveryFee) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has('delivery_fee')])>
                                        <span class="input-group-text">₼</span>
                                        @error('delivery_fee')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div id="deliveryThreshold" class="col-sm-6 {{ old('delivery_mode', $deliveryMode) === 'threshold' ? '' : 'd-none' }}">
                                    <label for="free_delivery_from" class="form-label">Pulsuz olduğu məbləğ</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">≥</span>
                                        <input id="free_delivery_from" name="free_delivery_from" type="number" min="0.01" step="0.01"
                                               value="{{ old('free_delivery_from', $freeDeliveryFrom) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has('free_delivery_from')])>
                                        <span class="input-group-text">₼</span>
                                        @error('free_delivery_from')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-sm-6">
                                    <label for="gift_wrap_mode" class="form-label">Hədiyyəlik bükülmə</label>
                                    <select id="gift_wrap_mode" name="gift_wrap_mode" class="form-select">
                                        <option value="free" @selected(old('gift_wrap_mode', $giftWrapMode) === 'free')>Pulsuz</option>
                                        <option value="paid" @selected(old('gift_wrap_mode', $giftWrapMode) === 'paid')>Pullu</option>
                                    </select>
                                </div>
                                <div id="giftWrapFeeField" class="col-sm-6 {{ old('gift_wrap_mode', $giftWrapMode) === 'paid' ? '' : 'd-none' }}">
                                    <label for="gift_wrap_fee" class="form-label">Bükülmə haqqı</label>
                                    <div class="input-group has-validation">
                                        <input id="gift_wrap_fee" name="gift_wrap_fee" type="number" min="0" step="0.01"
                                               value="{{ old('gift_wrap_fee', $giftWrapFee) }}"
                                               @class(['form-control', 'is-invalid' => $errors->has('gift_wrap_fee')])>
                                        <span class="input-group-text">₼</span>
                                        @error('gift_wrap_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="order_terms_url" class="form-label">Sifariş şərtlərinin URL-i</label>
                                <input id="order_terms_url" name="order_terms_url" type="url" maxlength="2048"
                                       placeholder="https://parfumshop.az/..."
                                       value="{{ old('order_terms_url', $orderTermsUrl) }}"
                                       @class(['form-control', 'is-invalid' => $errors->has('order_terms_url')])>
                                @error('order_terms_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Checkout səhifəsində “şərtlər” keçidi üçün istifadə olunur.</div>
                            </div>

                            <div class="mt-3">
                                <label for="credit_terms_url" class="form-label">Kredit şərtlərinin URL-i</label>
                                <input id="credit_terms_url" name="credit_terms_url" type="url" maxlength="2048"
                                       placeholder="https://parfumshop.az/..."
                                       value="{{ old('credit_terms_url', $creditTermsUrl) }}"
                                       @class(['form-control', 'is-invalid' => $errors->has('credit_terms_url')])>
                                @error('credit_terms_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Checkout-da “Hissə-hissə ödəniş” seçiləndə kredit şərtlərinin keçidi kimi göstərilir.</div>
                            </div>

                            <div class="form-text mt-3">
                                Bonus sifariş məbləğinin faizi kimi hesablanır (çatdırılma daxil deyil).
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Banner ölçüləri --}}
                <div class="col-xl-7">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-baseline justify-content-between gap-3 mb-3">
                                <h5 class="mb-0">Banner ölçüləri</h5>
                                <span class="text-muted small">Yeni bannerlər bu ölçülərə kəsiləcək</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 settings-banner-table">
                                    <thead>
                                    <tr class="text-muted small">
                                        <th class="fw-normal">Yer</th>
                                        <th class="fw-normal">En</th>
                                        <th class="fw-normal">Hündürlük</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ([
                                        'banner_web_top'       => 'Veb — yuxarı',
                                        'banner_web_bottom'    => 'Veb — aşağı',
                                        'banner_mobile_top'    => 'Mobil — yuxarı',
                                        'banner_mobile_bottom' => 'Mobil — aşağı',
                                    ] as $key => $label)
                                        <tr>
                                            <th scope="row" class="fw-medium text-nowrap pe-3">{{ $label }}</th>
                                            @foreach (['width' => 'En', 'height' => 'Hündürlük'] as $dimension => $caption)
                                                @php $field = "{$key}_{$dimension}"; @endphp
                                                <td>
                                                    <div class="input-group input-group-sm has-validation">
                                                        <input id="{{ $field }}" type="number" name="{{ $field }}"
                                                               min="1" max="10000" step="1"
                                                               aria-label="{{ $label }} — {{ $caption }}"
                                                               value="{{ old($field, $bannerSizes[$key][$dimension]) }}"
                                                               @class(['form-control', 'is-invalid' => $errors->has($field)]) required>
                                                        <span class="input-group-text">px</span>
                                                        @error($field)
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        .settings-banner-table td { min-width: 140px; }
        .settings-banner-table tr:last-child > * { border-bottom: 0; }
    </style>

    <script>
        (() => {
            const mode = document.getElementById('delivery_mode');
            const fields = document.getElementById('deliveryFields');
            const threshold = document.getElementById('deliveryThreshold');
            const feeInput = document.getElementById('delivery_fee');
            const thresholdInput = document.getElementById('free_delivery_from');

            function syncDeliveryFields() {
                const isFree = mode.value === 'free';
                const isThreshold = mode.value === 'threshold';

                fields.classList.toggle('d-none', isFree);
                threshold.classList.toggle('d-none', !isThreshold);
                feeInput.disabled = isFree;
                feeInput.required = !isFree;
                thresholdInput.disabled = !isThreshold;
                thresholdInput.required = isThreshold;
            }

            const giftMode = document.getElementById('gift_wrap_mode');
            const giftFeeField = document.getElementById('giftWrapFeeField');
            const giftFeeInput = document.getElementById('gift_wrap_fee');
            function syncGiftWrapFields() {
                const paid = giftMode.value === 'paid';
                giftFeeField.classList.toggle('d-none', !paid);
                giftFeeInput.disabled = !paid;
                giftFeeInput.required = paid;
            }
            giftMode.addEventListener('change', syncGiftWrapFields);
            syncGiftWrapFields();

            mode.addEventListener('change', syncDeliveryFields);
            syncDeliveryFields();
        })();
    </script>
@endsection
