{{--
  Ayarlar → Bannerlər. Sol: hər yer üçün kəsilmə ölçüsü; sağ: slayd keçidi və saytın sxemi (kompüter və telefon ekranında banner).
--}}
@php
    $bannerPlaces = [
        'banner_web_top' => 'Veb — yuxarı',
        'banner_web_bottom' => 'Veb — aşağı',
        'banner_mobile_top' => 'Mobil — yuxarı',
        'banner_mobile_bottom' => 'Mobil — aşağı',
    ];
    // Tövsiyə olunan ölçülər (Setting::BANNER_DIMENSIONS) — xana boşaldılanda placeholder kimi görünür
    $bannerPlaceholders = collect(\App\Models\Setting::BANNER_DIMENSIONS)->map(fn ($size) => ['width' => $size[0], 'height' => $size[1]]);
@endphp
<div class="row g-4">
    {{-- ========== Sol: ölçülər ========== --}}
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Banner ölçüləri</h5>
                    <span class="text-muted small">Yeni bannerlər bu ölçülərə kəsiləcək</span>
                </div>

                <div class="alert alert-info" role="note">
                    <strong class="d-block">Bu ölçülər saytda banner blokunun formasını da müəyyən edir.</strong>
                    Böyük rəqəm banneri keyfiyyətli etmir: veb bannerlər saytda ən çox {{ \App\Models\Setting::bannerLimits('banner_web_top')['display_width'] }} px,
                    mobil bannerlər {{ \App\Models\Setting::bannerLimits('banner_mobile_top')['display_width'] }} px enində göstərilir. Tövsiyə olunan ölçü bunun iki mislidir
                    (kəskin ekranlar üçün) — ondan böyük dəyər qəbul olunmur. Dəyişməzdən əvvəl dizaynerlə razılaşdırın.
                    <div class="mt-2">
                        <strong>Sayt açılanda ilk ekranda məhsullar görünməlidir.</strong> Müştəri sürüşdürmədən məhsul görmürsə, mağazaya yox, reklama baxdığını
                        düşünür və çıxır. Google da səhifəni ilk ekrana və onun yüklənmə sürətinə görə qiymətləndirir: banner ilk ekranın ən böyük şəklidir,
                        o nə qədər böyük və ağırdırsa, səhifə o qədər gec açılır və axtarışda mövqeyə mənfi təsir edir. Sağdakı sxem banner böyüdükcə
                        məhsulların ekrandan necə çıxdığını göstərir.
                    </div>
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
                        @foreach ($bannerPlaces as $key => $label)
                            @php $limits = \App\Models\Setting::bannerLimits($key); @endphp
                            <tr data-banner-limits="{{ $key }}" data-max-width="{{ $limits['max_width'] }}" data-min-width="{{ $limits['min_width'] }}"
                                data-display-width="{{ $limits['display_width'] }}" data-min-ratio="{{ $limits['min_ratio'] }}" data-max-ratio="{{ $limits['max_ratio'] }}">
                                <th scope="row" class="fw-medium text-nowrap pe-3">{{ $label }}
                                    <div class="text-muted text-small fw-normal">saytda {{ $limits['display_width'] }} px · ən çox {{ $limits['max_width'] }} px</div>
                                </th>
                                @foreach (['width' => 'En', 'height' => 'Hündürlük'] as $dimension => $caption)
                                    @php $field = "{$key}_{$dimension}"; @endphp
                                    <td>
                                        <div class="input-group input-group-sm has-validation">
                                            <input id="{{ $field }}" type="number" name="{{ $field }}" min="1" max="10000" step="1"
                                                   aria-label="{{ $label }} — {{ $caption }}"
                                                   value="{{ old($field, $bannerSizes[$key][$dimension]) }}"
                                                   placeholder="{{ $bannerPlaceholders[$key][$dimension] }}"
                                                   data-banner-size="{{ $key }}" data-banner-dimension="{{ $dimension }}"
                                                   @class(['form-control', 'is-invalid' => $errors->has($field)]) required>
                                            <span class="input-group-text">px</span>
                                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Yazarkən çıxan xəbərdarlıq (settings.js) --}}
                            <tr class="d-none" data-banner-warning="{{ $key }}"><td></td><td colspan="2" class="text-danger text-small pt-0 border-0"></td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-3">Ölçü dəyişəndə artıq yüklənmiş bannerlər yenidən kəsilmir — yalnız yeni yüklənənlərə tətbiq olunur.</div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-banner-reset>Tövsiyə olunan ölçülərə qaytar</button>
            </div>
        </div>
    </div>

    {{-- ========== Sağ: slayd keçidi və nisbət önizləməsi ========== --}}
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="settings-card-head">
                    <h5 class="mb-0">Göstərilmə</h5>
                </div>

                <label for="banner_slide_interval" class="form-label">Slayd keçidi</label>
                <div class="input-group has-validation">
                    <input id="banner_slide_interval" type="number" name="banner_slide_interval" min="2" max="60" step="1"
                           value="{{ old('banner_slide_interval', $bannerSlideInterval) }}"
                           @class(['form-control', 'is-invalid' => $errors->has('banner_slide_interval')]) required>
                    <span class="input-group-text">saniyə</span>
                    @error('banner_slide_interval')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text">Eyni yerdə bir neçə banner olanda hər biri neçə saniyə göstərilsin (2–60).</div>

                <hr class="settings-divider">

                {{-- Saytın sxemi: banner ölçüsü dəyişdikcə səhifəni necə tutduğu görünür (settings.js). Ölçülər real ekrandan götürülüb
                     (MacBook 1440×900, Chrome): brauzerin tabları və ünvan sətri 110 px tutur → sayta 790 px qalır; məzmun 1060 px;
                     başlıq 70, menyu 45, brendlər 63, "nəticə" sətri 37 px, məhsul kartı 257×420. Telefon: 390×844, brauzer üstdə 47,
                     altda 85 px; məzmun 350 px (təxmini ölçülər). Sxemin içində sürüşdürmək olur — aşağı banner məhsulların altındadır. --}}
                <div class="text-muted small mb-3">Saytda necə görünəcək</div>
                @php
                    $ratio = fn ($key) => max(1, (int) $bannerSizes[$key]['width']).' / '.max(1, (int) $bannerSizes[$key]['height']);
                @endphp
                <div class="mb-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Kompüter</span>
                        {{-- above: bannerdən başqa, kartlara qədər olan hər şey (başlıq 70 + menyu 45 + 11 + 15 + brendlər 63 + 25 + nəticə 37 + 16) --}}
                        <span class="text-muted" data-banner-screen="banner_web_top" data-screen-width="1060" data-screen-viewport="790" data-screen-above="282" data-screen-card="420" data-screen-limit="20"></span>
                    </div>
                    <div class="bn-device bn-device--desktop" data-banner-device="banner_web_top">
                        <div class="bn-chrome"><div class="bn-tabs"><i></i><i></i><i></i></div><div class="bn-url"></div></div>
                        <div class="bn-screen"><div class="bn-page">
                            <div class="bn-head"><i></i></div>
                            <div class="bn-nav"><i></i><i></i><i></i><i></i><i></i><i></i></div>
                            <div class="bn-banner is-top" data-banner-preview="banner_web_top" style="aspect-ratio: {{ $ratio('banner_web_top') }}"><span>Yuxarı banner · <b data-banner-label="banner_web_top">{{ $bannerSizes['banner_web_top']['width'] }}×{{ $bannerSizes['banner_web_top']['height'] }}</b></span></div>
                            <div class="bn-brands">@for($i = 0; $i < 8; $i++)<i></i>@endfor</div>
                            <div class="bn-body">
                                <div class="bn-side"><i></i><i></i><i class="is-tall"></i></div>
                                <div><div class="bn-result"><i></i><i></i></div><div class="bn-grid">@for($i = 0; $i < 6; $i++)<i></i>@endfor</div></div>
                            </div>
                            <div class="bn-banner" data-banner-preview="banner_web_bottom" style="aspect-ratio: {{ $ratio('banner_web_bottom') }}"><span>Aşağı banner · <b data-banner-label="banner_web_bottom">{{ $bannerSizes['banner_web_bottom']['width'] }}×{{ $bannerSizes['banner_web_bottom']['height'] }}</b></span></div>
                            <div class="bn-bar is-footer"></div>
                        </div></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Telefon</span>
                        <span class="text-muted" data-banner-screen="banner_mobile_top" data-screen-width="350" data-screen-viewport="712" data-screen-above="254" data-screen-card="300" data-screen-limit="50"></span>
                    </div>
                    <div class="bn-device bn-device--phone" data-banner-device="banner_mobile_top">
                        <div class="bn-chrome"></div>
                        <div class="bn-screen"><div class="bn-page">
                            <div class="bn-head"><i></i></div>
                            <div class="bn-nav"><i></i><i></i><i></i></div>
                            <div class="bn-banner is-top" data-banner-preview="banner_mobile_top" style="aspect-ratio: {{ $ratio('banner_mobile_top') }}"><span>Yuxarı · <b data-banner-label="banner_mobile_top">{{ $bannerSizes['banner_mobile_top']['width'] }}×{{ $bannerSizes['banner_mobile_top']['height'] }}</b></span></div>
                            <div class="bn-brands">@for($i = 0; $i < 3; $i++)<i></i>@endfor</div>
                            <div class="bn-result"><i></i><i></i></div>
                            <div class="bn-grid">@for($i = 0; $i < 4; $i++)<i></i>@endfor</div>
                            <div class="bn-banner" data-banner-preview="banner_mobile_bottom" style="aspect-ratio: {{ $ratio('banner_mobile_bottom') }}"><span>Aşağı · <b data-banner-label="banner_mobile_bottom">{{ $bannerSizes['banner_mobile_bottom']['width'] }}×{{ $bannerSizes['banner_mobile_bottom']['height'] }}</b></span></div>
                            <div class="bn-bar is-footer"></div>
                        </div></div>
                        <div class="bn-chrome is-bottom"><div class="bn-url"></div></div>
                    </div>
                </div>
                <div class="form-text mt-3">Çərçivə bütün ekrandır: yuxarıda brauzerin tabları və ünvan sətri, altında sayt. Sxemin içində sürüşdürmək olur.</div>
            </div>
        </div>
    </div>
</div>
