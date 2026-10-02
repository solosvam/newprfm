{{--
  Ayarlar → Bonuslar. Sol: məbləğlər və hədd; sağ: müştəriyə göstəriləcək bonus şərtləri (3 dildə).
  Şərtlərdə :percent və :registration hazırkı dəyərlərlə əvəz olunur (BonusService::terms).
--}}
@php
    $registrationOn = (string) old('registration_bonus_enabled', (int) $registrationBonusEnabled) === '1';
    $termLocales = ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский'];
    $termsTab = collect(array_keys($termLocales))->first(fn ($l) => $errors->has('bonus_terms_'.$l)) ?? 'az';
@endphp
<div class="row g-4">
    {{-- ========== Sol: bonus ayarları ========== --}}
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Bonus ayarları</h5>
                </div>

                <div class="mb-4">
                    <label for="order_bonus_percent" class="form-label">Sifariş bonusu</label>
                    <div class="input-group has-validation">
                        <input id="order_bonus_percent" type="number" step="0.01" min="0" max="100" name="order_bonus_percent"
                               value="{{ old('order_bonus_percent', $bonusPercent) }}"
                               @class(['form-control', 'is-invalid' => $errors->has('order_bonus_percent')]) required>
                        <span class="input-group-text">%</span>
                        @error('order_bonus_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Sifariş məbləğindən (çatdırılmasız, endirim çıxılmaqla) hesablanır.</div>
                </div>

                <hr class="settings-divider">

                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label for="registration_bonus_amount" class="form-label mb-0">Qeydiyyat bonusu</label>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="registration_bonus_enabled" value="0">
                            <input id="registration_bonus_enabled" name="registration_bonus_enabled" value="1"
                                   type="checkbox" role="switch" class="form-check-input" aria-label="Qeydiyyat bonusu aktivdir"
                                   @checked($registrationOn)>
                        </div>
                    </div>
                    <div class="input-group has-validation">
                        <input id="registration_bonus_amount" type="number" name="registration_bonus_amount" min="0" max="10000" step="0.01"
                               value="{{ old('registration_bonus_amount', $registrationBonusAmount) }}"
                               @class(['form-control', 'is-invalid' => $errors->has('registration_bonus_amount')])>
                        <span class="input-group-text">₼</span>
                        @error('registration_bonus_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Yalnız yeni müştəri qeydiyyatdan keçəndə verilir.</div>
                </div>

                <hr class="settings-divider">

                {{-- Bonusla ödəniş həddi: sifarişin ən çox neçə faizi bonusla ödənə bilər (bütün bonuslar) --}}
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label for="bonus_pay_percent" class="form-label mb-0">Bonusla ödəniş həddi</label>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="bonus_pay_limit_enabled" value="0">
                            <input id="bonus_pay_limit_enabled" name="bonus_pay_limit_enabled" value="1" type="checkbox" role="switch"
                                   class="form-check-input" aria-label="Bonusla ödəniş həddi aktivdir"
                                   data-settings-toggle="payLimit" @checked((string) old('bonus_pay_limit_enabled', (int) $bonusPayLimitEnabled) === '1')>
                        </div>
                    </div>
                    <div class="input-group has-validation" data-settings-target="payLimit">
                        <span class="input-group-text">ən çox</span>
                        <input id="bonus_pay_percent" name="bonus_pay_percent" type="number" min="1" max="100" step="1"
                               value="{{ old('bonus_pay_percent', $bonusPayPercent) }}"
                               @class(['form-control', 'is-invalid' => $errors->has('bonus_pay_percent')])>
                        <span class="input-group-text">%</span>
                        @error('bonus_pay_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Məsələn 30%: 90 ₼-lıq səbətin ən çox 27 ₼-u bonusla ödənir. Söndürülübsə, bonus balansı imkan verdikcə tam ödəmək olar.</div>
                </div>

                <hr class="settings-divider">

                {{-- Bonusun istifadə müddəti: müddət ərzində xərclənməyən bonus balansdan silinir (referal bonusunun müddəti Referal bölməsindədir) --}}
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="form-label mb-0">Bonusun istifadə müddəti</span>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="bonus_expiry_enabled" value="0">
                            <input id="bonus_expiry_enabled" name="bonus_expiry_enabled" value="1" type="checkbox" role="switch"
                                   class="form-check-input" aria-label="Bonusun istifadə müddəti aktivdir"
                                   data-settings-toggle="bonusExpiry" @checked((string) old('bonus_expiry_enabled', (int) $bonusExpiryEnabled) === '1')>
                        </div>
                    </div>
                    <div class="row g-3" data-settings-target="bonusExpiry">
                        @foreach(['bonus_order_expiry_days' => ['Sifariş bonusu', $bonusOrderExpiryDays], 'bonus_registration_expiry_days' => ['Qeydiyyat bonusu', $bonusRegistrationExpiryDays]] as $field => [$label, $value])
                            <div class="col-sm-6">
                                <label for="{{ $field }}" class="form-label small text-muted mb-1">{{ $label }}</label>
                                <div class="input-group has-validation">
                                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="1" max="3650" step="1"
                                           value="{{ old($field, $value) }}" @class(['form-control', 'is-invalid' => $errors->has($field)])>
                                    <span class="input-group-text">gün</span>
                                    @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Bonus qazanıldığı gündən bu müddət ərzində istifadə olunmasa, balansdan silinir. Söndürülübsə, bonus müddətsizdir. Referal bonusunun müddəti Referal bölməsində təyin olunur.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== Sağ: bonus şərtləri (3 dildə) ========== --}}
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Bonus şərtləri</h5>
                    <span class="text-muted small">Müştəriyə göstərilir</span>
                </div>

                <ul class="nav nav-tabs nav-tabs-line mb-3" role="tablist">
                    @foreach($termLocales as $locale => $label)
                        <li class="nav-item" role="presentation">
                            <button type="button" role="tab" data-bs-toggle="tab" data-bs-target="#bonusTerms_{{ $locale }}"
                                    @class(['nav-link', 'active' => $termsTab === $locale, 'text-danger' => $errors->has('bonus_terms_'.$locale)])>{{ $label }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">
                    @foreach($termLocales as $locale => $label)
                        @php $field = 'bonus_terms_'.$locale; @endphp
                        <div id="bonusTerms_{{ $locale }}" role="tabpanel" @class(['tab-pane fade', 'show active' => $termsTab === $locale])>
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="14" maxlength="5000" aria-label="Bonus şərtləri — {{ $label }}"
                                      @class(['form-control settings-terms', 'is-invalid' => $errors->has($field)])>{{ old($field, $bonusTerms[$locale]) }}</textarea>
                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="form-text mt-2">
                    <code>:percent</code> — sifariş bonusu faizi, <code>:registration</code> — qeydiyyat bonusu məbləği ilə avtomatik əvəz olunur.
                    Hər sətir ayrıca abzas kimi göstərilir; "• " ilə başlayan sətirlər siyahı olur.
                </div>
            </div>
        </div>
    </div>
</div>
