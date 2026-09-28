@php $title = 'Ayarlar'; @endphp
@extends('backend.layout', ['title' => $title])

@section('content')
    @php
        $deliveryModeValue = old('delivery_mode', $deliveryMode);
        $giftWrapModeValue = old('gift_wrap_mode', $giftWrapMode);
        $registrationOn    = (string) old('registration_bonus_enabled', (int) $registrationBonusEnabled) === '1';
    @endphp

    <div class="container">
        <div class="page-title-container d-flex align-items-center justify-content-between mb-4">
            <h1 class="mb-0 pb-0 display-4">Ayarlar</h1>
            <button type="submit" form="settingsForm" class="btn btn-primary">Yadda saxla</button>
        </div>

        <form id="settingsForm" method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <div class="row g-4">
                {{-- ================= SOL ================= --}}
                <div class="col-xl-6 d-flex flex-column gap-4">

                    {{-- Bonuslar --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="settings-card-head">
                                <h5 class="mb-0">Bonuslar</h5>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="order_bonus_percent" class="form-label">Sifariş bonusu</label>
                                    <div class="input-group has-validation">
                                        <input id="order_bonus_percent" type="number" step="0.01" min="0" max="100"
                                               name="order_bonus_percent"
                                               value="{{ old('order_bonus_percent', $bonusPercent) }}"
                                               @class(['form-control', 'is-invalid' => $errors->has('order_bonus_percent')]) required>
                                        <span class="input-group-text">%</span>
                                        @error('order_bonus_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">Sifariş məbləğindən (çatdırılmasız) hesablanır.</div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label for="registration_bonus_amount" class="form-label mb-0">Qeydiyyat bonusu</label>
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="registration_bonus_enabled" value="0">
                                            <input id="registration_bonus_enabled" name="registration_bonus_enabled" value="1"
                                                   type="checkbox" role="switch" class="form-check-input"
                                                   aria-label="Qeydiyyat bonusu aktivdir"
                                                @checked($registrationOn)>
                                        </div>
                                    </div>
                                    <div class="input-group has-validation">
                                        <input id="registration_bonus_amount" type="number" name="registration_bonus_amount"
                                               min="0" max="10000" step="0.01"
                                               value="{{ old('registration_bonus_amount', $registrationBonusAmount) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has('registration_bonus_amount')])>
                                        <span class="input-group-text">₼</span>
                                        @error('registration_bonus_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">Yalnız yeni müştəri qeydiyyatdan keçəndə verilir.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Çatdırılma və bükülmə --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="settings-card-head">
                                <h5 class="mb-0">Çatdırılma və bükülmə</h5>
                            </div>

                            {{-- Çatdırılma --}}
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="delivery_mode" class="form-label">Çatdırılma qaydası</label>
                                    <select id="delivery_mode" name="delivery_mode"
                                        @class(['form-select', 'is-invalid' => $errors->has('delivery_mode')])>
                                        <option value="free" @selected($deliveryModeValue === 'free')>Tam pulsuz</option>
                                        <option value="paid" @selected($deliveryModeValue === 'paid')>Pullu çatdırılma</option>
                                        <option value="threshold" @selected($deliveryModeValue === 'threshold')>Məbləğdən yuxarı pulsuz</option>
                                    </select>
                                    @error('delivery_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div id="deliveryFeeField" @class(['col-sm-6', 'd-none' => $deliveryModeValue === 'free'])>
                                    <label for="delivery_fee" class="form-label">Çatdırılma haqqı</label>
                                    <div class="input-group has-validation">
                                        <input id="delivery_fee" name="delivery_fee" type="number" min="0" step="0.01"
                                               value="{{ old('delivery_fee', $deliveryFee) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has('delivery_fee')])>
                                        <span class="input-group-text">₼</span>
                                        @error('delivery_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div id="deliveryThreshold" @class(['col-sm-6', 'd-none' => $deliveryModeValue !== 'threshold'])>
                                    <label for="free_delivery_from" class="form-label">Pulsuz olduğu məbləğ</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">≥</span>
                                        <input id="free_delivery_from" name="free_delivery_from" type="number" min="0.01" step="0.01"
                                               value="{{ old('free_delivery_from', $freeDeliveryFrom) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has('free_delivery_from')])>
                                        <span class="input-group-text">₼</span>
                                        @error('free_delivery_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <hr class="settings-divider">

                            {{-- Hədiyyəlik bükülmə --}}
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="gift_wrap_mode" class="form-label">Hədiyyəlik bükülmə</label>
                                    <select id="gift_wrap_mode" name="gift_wrap_mode" class="form-select">
                                        <option value="free" @selected($giftWrapModeValue === 'free')>Pulsuz</option>
                                        <option value="paid" @selected($giftWrapModeValue === 'paid')>Pullu</option>
                                    </select>
                                </div>
                                <div id="giftWrapFeeField" @class(['col-sm-6', 'd-none' => $giftWrapModeValue !== 'paid'])>
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
                        </div>
                    </div>
                </div>

                {{-- ================= SAĞ ================= --}}
                <div class="col-xl-6 d-flex flex-column gap-4">

                    {{-- Banner ölçüləri --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="settings-card-head">
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
                                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                    {{-- Şərt keçidləri --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="settings-card-head">
                                <h5 class="mb-0">Şərt keçidləri</h5>
                                <span class="text-muted small">Checkout səhifəsində göstərilir</span>
                            </div>

                            @foreach ([
                                'order_terms_url'  => ['Sifariş şərtləri', $orderTermsUrl, 'Checkout-dakı “şərtlər” keçidi.'],
                                'credit_terms_url' => ['Kredit şərtləri', $creditTermsUrl, '“Hissə-hissə ödəniş” seçiləndə göstərilir.'],
                            ] as $field => [$label, $value, $hint])
                                <div @class(['mb-3' => ! $loop->last])>
                                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                    <div class="input-group has-validation">
                                        <input id="{{ $field }}" name="{{ $field }}" type="url" maxlength="2048"
                                               placeholder="https://parfumshop.az/..."
                                               value="{{ old($field, $value) }}"
                                            @class(['form-control', 'is-invalid' => $errors->has($field)])>
                                        <button type="button" class="btn btn-outline-secondary" data-open-url="{{ $field }}">Aç</button>
                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">{{ $hint }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        .settings-card-head {
            display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
            margin-bottom: 1.25rem;
        }
        .settings-divider { margin: 1.5rem 0; opacity: .08; }
        .settings-banner-table td { min-width: 130px; }
        .settings-banner-table tr:last-child > * { border-bottom: 0; }
        #settingsForm .form-switch .form-check-input { width: 2.25em; height: 1.2em; cursor: pointer; }
    </style>

    <script>
        (() => {
            const $ = id => document.getElementById(id);

            function toggleField(wrapper, input, visible) {
                wrapper?.classList.toggle('d-none', !visible);
                if (input) {
                    input.disabled = !visible;
                    input.required = visible;
                }
            }

            // Çatdırılma
            const deliveryMode = $('delivery_mode');
            function syncDelivery() {
                const mode = deliveryMode.value;
                toggleField($('deliveryFeeField'), $('delivery_fee'), mode !== 'free');
                toggleField($('deliveryThreshold'), $('free_delivery_from'), mode === 'threshold');
            }
            deliveryMode.addEventListener('change', syncDelivery);
            syncDelivery();

            // Hədiyyəlik bükülmə
            const giftMode = $('gift_wrap_mode');
            function syncGiftWrap() {
                toggleField($('giftWrapFeeField'), $('gift_wrap_fee'), giftMode.value === 'paid');
            }
            giftMode.addEventListener('change', syncGiftWrap);
            syncGiftWrap();

            // Qeydiyyat bonusu
            const registrationSwitch = $('registration_bonus_enabled');
            const registrationAmount = $('registration_bonus_amount');
            function syncRegistration() {
                registrationAmount.disabled = !registrationSwitch.checked;
                registrationAmount.required = registrationSwitch.checked;
            }
            registrationSwitch.addEventListener('change', syncRegistration);
            syncRegistration();

            // URL-i yeni tabda aç
            document.querySelectorAll('[data-open-url]').forEach(button => {
                button.addEventListener('click', () => {
                    const url = $(button.dataset.openUrl)?.value.trim();
                    if (url && /^https?:\/\//i.test(url)) window.open(url, '_blank', 'noopener');
                });
            });
        })();
    </script>
@endsection
