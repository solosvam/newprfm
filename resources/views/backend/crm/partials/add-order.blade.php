{{--
    CRM → "Yeni sifariş" modalının məzmunu.
    Məlumat: CrmController::customer() → $orderForm
    Axtarış: AjaxController::searchProductCrm · Saxlama: CrmController::storeOrder
--}}
@php
    $giftFee = (float) ($orderForm['giftWrap']['fee'] ?? 0);
@endphp

<div class="crm-order"
     data-search-url="{{ route('admin.ajax.search.product.crm') }}"
     data-store-url="{{ route('admin.crm.order.store', $customer) }}"
     data-delivery='@json($orderForm['delivery'])'
     data-gift-fee="{{ $giftFee }}"
     data-bonus-rate="{{ $orderForm['bonusRate'] }}"
     data-bonus-balance="{{ $orderForm['bonusBalance'] }}">

    <div class="row g-4">
        {{-- 1. Məhsul axtarışı --}}
        <div class="col-12 col-lg-7">
            <h6 class="crm-order__step"><span>1</span> Məhsullar</h6>
            <div class="position-relative mb-3">
                <i data-acorn-icon="search" data-acorn-size="16" class="crm-order__search-icon"></i>
                <input type="search" class="form-control crm-order__search" id="crmOrderSearch"
                       placeholder="Brend və ya məhsul adı (məs. “dol devotion”)" autocomplete="off">
            </div>
            <div id="crmOrderResults" class="crm-order__results">
                <div class="crm-order__empty">Axtarış nəticələri burada görünəcək.</div>
            </div>
        </div>

        {{-- Səbət --}}
        <div class="col-12 col-lg-5">
            <div class="crm-order__cart">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Səbət</h6>
                    <span class="badge bg-outline-primary" id="crmOrderCount">0</span>
                </div>
                <div id="crmOrderCart">
                    <div class="crm-order__empty">Səbət boşdur. Soldan məhsul əlavə edin.</div>
                </div>
                <dl class="crm-order__totals">
                    <div><dt>Toplam</dt><dd id="crmOrderSubtotal">0.00 ₼</dd></div>
                    <div id="crmOrderDiscountRow" class="crm-order__discount" hidden><dt>Endirim</dt><dd id="crmOrderDiscount">—</dd></div>
                    <div><dt>Çatdırılma</dt><dd id="crmOrderDelivery">—</dd></div>
                    <div id="crmOrderGiftRow" hidden><dt>Hədiyyəlik bükmə</dt><dd id="crmOrderGift">—</dd></div>
                    <div id="crmOrderCreditRow" hidden><dt>Kredit faizi</dt><dd id="crmOrderCreditFee">—</dd></div>
                    <div class="crm-order__grand"><dt>Yekun</dt><dd id="crmOrderTotal">0.00 ₼</dd></div>
                </dl>
                <div class="crm-order__bonus" id="crmOrderBonus" hidden></div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="row g-4">
        {{-- 2. Ünvan --}}
        <div class="col-12 col-lg-6">
            <h6 class="crm-order__step"><span>2</span> Çatdırılma ünvanı</h6>
            <select class="form-select" id="crmOrderAddress">
                @foreach($orderForm['addresses'] as $a)
                    <option value="{{ $a->id }}">{{ $a->label }}</option>
                @endforeach
                <option value="new" @selected($orderForm['addresses']->isEmpty())>+ Yeni ünvan əlavə et</option>
            </select>

            {{-- Ünvanın adı yoxdur: controller "Ünvan #N" verir --}}
            <div id="crmOrderNewAddress" class="row g-2 mt-1" @if($orderForm['addresses']->isNotEmpty()) hidden @endif>
                <div class="col-12">
                    <select class="form-select crm-city-select" name="city_id" data-placeholder="Şəhər seçin *">
                        <option value=""></option>
                        @foreach($orderForm['cities'] as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12"><input type="text" class="form-control" name="address" maxlength="500" placeholder="Küçə və ünvan *"></div>
                <div class="col-12">
                    <button type="button" class="btn btn-link p-0 crm-order__details-toggle" id="crmOrderDetailsToggle" aria-expanded="false">
                        + Ətraflı: bina, blok, mərtəbə, mənzil
                    </button>
                </div>
                <div class="col-12" id="crmOrderAddressDetails" hidden>
                    <div class="row g-2">
                        <div class="col-6"><input type="text" class="form-control" name="building" maxlength="50" placeholder="Bina"></div>
                        <div class="col-6"><input type="text" class="form-control" name="entrance" maxlength="50" placeholder="Blok"></div>
                        <div class="col-6"><input type="text" class="form-control" name="floor" maxlength="30" placeholder="Mərtəbə"></div>
                        <div class="col-6"><input type="text" class="form-control" name="apartment" maxlength="30" placeholder="Mənzil"></div>
                    </div>
                </div>
                <div class="col-12"><textarea class="form-control" name="address_note" rows="2" maxlength="1000" placeholder="Əlavə məlumat (kuryer üçün qeyd, orientir...)"></textarea></div>
            </div>

            <h6 class="crm-order__step mt-4"><span>3</span> Əlavə seçimlər</h6>
            <label class="crm-order__check">
                <input type="checkbox" class="form-check-input" id="crmOrderGiftWrap">
                <span>Hədiyyəlik bükülsün</span>
                <small class="text-muted ms-auto">{{ $giftFee > 0 ? '+' . number_format($giftFee, 2) . ' ₼' : 'Pulsuz' }}</small>
            </label>
        </div>

        {{-- 4. Ödəniş --}}
        <div class="col-12 col-lg-6">
            <h6 class="crm-order__step"><span>4</span> Ödəniş üsulu</h6>
            <div class="crm-order__payments" role="radiogroup">
                @foreach($orderForm['paymentMethods'] as $m)
                    @php $locked = $m->code === 'installment' && !$orderForm['creditReady']; @endphp
                    <label class="crm-pay {{ $locked ? 'is-locked' : '' }}">
                        <input type="radio" name="crm_payment_method" value="{{ $m->id }}" data-code="{{ $m->code }}"
                               @disabled($locked)>
                        <span class="crm-pay__title">{{ $m->localized_name }}</span>
                        @if($m->code === 'bonus_balance')
                            <span class="crm-pay__hint" data-bonus-hint>Balans: {{ number_format($orderForm['bonusBalance'], 2) }} ₼</span>
                        @elseif($locked)
                            <span class="crm-pay__hint text-danger">Kredit profili tamamlanmayıb</span>
                        @elseif(in_array($m->code, ['card_online', 'birbank_installment'], true))
                            <span class="crm-pay__hint">Müştəri profilindən ödəyəcək</span>
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="crm-order__months" id="crmOrderBirbank" hidden>
                <div class="crm-order__months-label">Birbank taksit müddəti</div>
                <div class="crm-order__periods">
                    @foreach([2, 3, 6] as $months)
                        <input type="radio" class="btn-check" name="crm_birbank_months" id="crmBirbank{{ $months }}" value="{{ $months }}">
                        <label class="btn btn-outline-primary" for="crmBirbank{{ $months }}">{{ $months }} ay</label>
                    @endforeach
                </div>
            </div>

            <div class="crm-order__months" id="crmOrderInstallment" hidden>
                <div class="crm-order__months-label">Hissə-hissə ödəniş müddəti</div>
                <div class="crm-order__periods">
                    @foreach($orderForm['creditPeriods'] as $period)
                        @php $rate = (float) $period->interest_rate; @endphp
                        <input type="radio" class="btn-check" name="crm_credit_period" id="crmPeriod{{ $period->id }}"
                               value="{{ $period->id }}" data-months="{{ $period->month }}" data-rate="{{ $rate }}">
                        <label class="btn btn-outline-primary" for="crmPeriod{{ $period->id }}">
                            <span class="d-block fw-bold">{{ $period->month }} ay</span>
                            <small class="d-block">{{ $rate == 0 ? 'Faizsiz' : '+' . rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%' }}</small>
                        </label>
                    @endforeach
                </div>
                <dl class="crm-order__credit" id="crmOrderCredit" hidden>
                    <div><dt>Aylıq ödəniş</dt><dd data-credit-monthly>—</dd></div>
                    <div><dt>Ümumi məbləğ</dt><dd data-credit-total>—</dd></div>
                    <div><dt>Faiz məbləği</dt><dd data-credit-overpay>—</dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>
