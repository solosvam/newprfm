{{-- Ayarlar → Sifariş və çatdırılma --}}
@php
    $deliveryModeValue = old('delivery_mode', $deliveryMode);
    $giftWrapModeValue = old('gift_wrap_mode', $giftWrapMode);
@endphp
<div class="row g-4">
    <div class="col-xl-6">
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
    <div class="col-xl-6">
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
