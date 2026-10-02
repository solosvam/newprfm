{{--
  Ayarlar → "Dostunu dəvət et" (referal). Dəyərlər App\Services\Referral\ReferralSettings-dən gəlir.
  Asılı sahələr data-settings-toggle ilə gizlənir (settings-referral.js); gizlədilən sahənin dəyəri yadda qalır.
--}}
@use('App\Services\Referral\ReferralSettings')
@php
    $r = fn (string $key) => old($key, $referral->raw($key));
    $on = fn (string $key) => (string) old($key, (int) $referral->raw($key)) === '1';
    $switch = function (string $key, string $label) use ($on) {
        return ['key' => $key, 'label' => $label, 'checked' => $on($key)];
    };
    $mode = $r('referral_invitee_mode');
@endphp

<div class="card" id="referralSettings">
    <div class="card-body">
        <div class="settings-card-head">
            <h5 class="mb-0">Dostunu dəvət et (referal)</h5>
            <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                <input type="hidden" name="referral_enabled" value="0">
                <input id="referral_enabled" name="referral_enabled" value="1" type="checkbox" role="switch"
                       class="form-check-input" @checked($on('referral_enabled'))>
                <label for="referral_enabled" class="form-check-label small">Proqram aktivdir</label>
            </div>
        </div>
        <p class="text-muted small mb-4">
            Dəvət olunan müştəri linklə qeydiyyatdan keçib <b>ilk sifarişi kuryer tərəfindən təhvil verildikdə</b> bonuslar yazılır.
        </p>

        <div class="row g-4">
            {{-- ========== Sol: məbləğlər və şərtlər ========== --}}
            <div class="col-xl-6">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="referral_referrer_amount" class="form-label">Dəvət edənin bonusu</label>
                        <div class="input-group has-validation">
                            <input id="referral_referrer_amount" name="referral_referrer_amount" type="number" min="0" step="0.01"
                                   value="{{ $r('referral_referrer_amount') }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('referral_referrer_amount')])>
                            <span class="input-group-text">₼</span>
                            @error('referral_referrer_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">Bonus balansına yazılır.</div>
                    </div>
                    <div class="col-sm-6">
                        <label for="referral_invitee_amount" class="form-label">Dəvət olunanın bonusu</label>
                        <div class="input-group has-validation">
                            <input id="referral_invitee_amount" name="referral_invitee_amount" type="number" min="0" step="0.01"
                                   value="{{ $r('referral_invitee_amount') }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('referral_invitee_amount')])>
                            <span class="input-group-text">₼</span>
                            @error('referral_invitee_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Dəvət olunanın bonusu necə verilsin</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach([ReferralSettings::MODE_DISCOUNT => 'İlk sifarişdə endirim (səbətdə görünür)', ReferralSettings::MODE_BALANCE => 'Bonus balansına'] as $value => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="referral_invitee_mode" id="referral_mode_{{ $value }}"
                                           value="{{ $value }}" @checked($mode === $value)>
                                    <label class="form-check-label" for="referral_mode_{{ $value }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12" data-referral-show="discount">
                        <div class="form-check form-switch">
                            <input type="hidden" name="referral_discount_with_promo" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="referral_discount_with_promo"
                                   name="referral_discount_with_promo" value="1" @checked($on('referral_discount_with_promo'))>
                            <label class="form-check-label" for="referral_discount_with_promo">Endirim promo kodla birlikdə işləsin</label>
                        </div>
                        <div class="form-text">Söndürülübsə, müştəri ya referal endirimini, ya promo kodu istifadə edir.</div>
                    </div>

                    <div class="col-12"><hr class="settings-divider my-1"></div>

                    {{-- Minimum sifariş --}}
                    <div class="col-sm-6">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="referral_min_order_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="referral_min_order_enabled"
                                   name="referral_min_order_enabled" value="1" data-settings-toggle="minOrder" @checked($on('referral_min_order_enabled'))>
                            <label class="form-check-label" for="referral_min_order_enabled">Minimum sifariş məbləği</label>
                        </div>
                        <div class="input-group has-validation" data-settings-target="minOrder">
                            <span class="input-group-text">≥</span>
                            <input id="referral_min_order_amount" name="referral_min_order_amount" type="number" min="0" step="0.01"
                                   value="{{ $r('referral_min_order_amount') }}" aria-label="Minimum sifariş məbləği"
                                   @class(['form-control', 'is-invalid' => $errors->has('referral_min_order_amount')])>
                            <span class="input-group-text">₼</span>
                            @error('referral_min_order_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Bonusun istifadə müddəti --}}
                    <div class="col-12">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="referral_expiry_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="referral_expiry_enabled"
                                   name="referral_expiry_enabled" value="1" data-settings-toggle="expiry" @checked($on('referral_expiry_enabled'))>
                            <label class="form-check-label" for="referral_expiry_enabled">Bonusun istifadə müddəti var</label>
                        </div>
                        <div class="row g-3" data-settings-target="expiry">
                            @foreach(['referral_referrer_expiry_days' => 'Dəvət edənin bonusu', 'referral_invitee_expiry_days' => 'Dəvət olunanın bonusu'] as $field => $label)
                                <div class="col-sm-6">
                                    <label for="{{ $field }}" class="form-label small text-muted mb-1">{{ $label }}</label>
                                    <div class="input-group has-validation">
                                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="1" max="3650" step="1"
                                               value="{{ $r($field) }}" @class(['form-control', 'is-invalid' => $errors->has($field)])>
                                        <span class="input-group-text">gün</span>
                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            @endforeach
                            <div class="col-12 form-text mt-1">Müddət ərzində istifadə olunmayan bonus balansdan silinir.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========== Sağ: limit, kimlər, paylaşma ========== --}}
            <div class="col-xl-6">
                <div class="row g-3">
                    {{-- Dəvət limiti --}}
                    <div class="col-12">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="referral_limit_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="referral_limit_enabled"
                                   name="referral_limit_enabled" value="1" data-settings-toggle="limit" @checked($on('referral_limit_enabled'))>
                            <label class="form-check-label" for="referral_limit_enabled">Dəvət limiti</label>
                        </div>
                        <div class="row g-3" data-settings-target="limit">
                            <div class="col-sm-5">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">ən çox</span>
                                    <input id="referral_limit_count" name="referral_limit_count" type="number" min="1" step="1"
                                           value="{{ $r('referral_limit_count') }}" aria-label="Dəvət limiti"
                                           @class(['form-control', 'is-invalid' => $errors->has('referral_limit_count')])>
                                    <span class="input-group-text">nəfər</span>
                                    @error('referral_limit_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-sm-7">
                                <select name="referral_limit_mode" class="form-select" aria-label="Limit dolanda">
                                    <option value="{{ ReferralSettings::LIMIT_NO_REWARD }}" @selected($r('referral_limit_mode') === ReferralSettings::LIMIT_NO_REWARD)>
                                        Limit dolanda: link işləyir, dəvət edən bonus almır
                                    </option>
                                    <option value="{{ ReferralSettings::LIMIT_BLOCK }}" @selected($r('referral_limit_mode') === ReferralSettings::LIMIT_BLOCK)>
                                        Limit dolanda: link bağlanır
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        @foreach([
                            $switch('referral_installment_allowed', 'Hissə-hissə ödənişli ilk sifariş də sayılsın'),
                            $switch('referral_inviter_requires_order', 'Dəvət etmək üçün ən azı 1 təhvil alınmış sifariş tələb olunsun'),
                        ] as $item)
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="{{ $item['key'] }}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $item['key'] }}"
                                       name="{{ $item['key'] }}" value="1" @checked($item['checked'])>
                                <label class="form-check-label" for="{{ $item['key'] }}">{{ $item['label'] }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="col-sm-6">
                        <label for="referral_cookie_days" class="form-label">Link yadda qalsın</label>
                        <div class="input-group has-validation">
                            <input id="referral_cookie_days" name="referral_cookie_days" type="number" min="1" max="365" step="1"
                                   value="{{ $r('referral_cookie_days') }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('referral_cookie_days')])>
                            <span class="input-group-text">gün</span>
                            @error('referral_cookie_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">Dost linki açıb bu müddət ərzində qeydiyyatdan keçsə, dəvət sayılır.</div>
                    </div>

                    <div class="col-12"><hr class="settings-divider my-1"></div>

                    {{-- Paylaşma --}}
                    <div class="col-12">
                        <label class="form-label">Paylaşma mətni</label>
                        @foreach(['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU'] as $locale => $label)
                            @php $field = 'referral_share_text_'.$locale; @endphp
                            <div class="input-group has-validation mb-2">
                                <span class="input-group-text">{{ $label }}</span>
                                <input id="{{ $field }}" name="{{ $field }}" type="text" maxlength="300" value="{{ $r($field) }}"
                                       aria-label="Paylaşma mətni {{ $label }}"
                                       @class(['form-control', 'is-invalid' => $errors->has($field)])>
                                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="form-text"><code>:amount</code> — dəvət olunanın bonusu ilə əvəz olunur. Linki sistem mətnin sonuna əlavə edir.</div>
                    </div>

                    <div class="col-12">
                        <label for="referral_og_image_file" class="form-label">Paylaşma şəkli (og-image)</label>
                        <div class="d-flex gap-3 align-items-start">
                            @if($referral->ogImageUrl())
                                <img src="{{ $referral->ogImageUrl() }}" alt="" class="rounded border settings-og-preview">
                            @endif
                            <div class="flex-grow-1">
                                <input id="referral_og_image_file" name="referral_og_image_file" type="file" accept="image/jpeg,image/png,image/webp"
                                       @class(['form-control', 'is-invalid' => $errors->has('referral_og_image_file')])>
                                @error('referral_og_image_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">WhatsApp, Telegram, Facebook önizləməsi. Tövsiyə: 1200×630 px, JPG/PNG/WEBP, 4 MB-a qədər.</div>
                                @if($referral->ogImageUrl())
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="referral_og_image_remove" name="referral_og_image_remove" value="1">
                                        <label class="form-check-label small" for="referral_og_image_remove">Şəkli sil</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
