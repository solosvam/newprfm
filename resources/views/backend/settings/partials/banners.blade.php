{{--
  Ayarlar → Bannerlər. Sol: hər yer üçün kəsilmə ölçüsü; sağ: slayd keçidi və ölçülərin nisbət önizləməsi.
--}}
@php
    $bannerPlaces = [
        'banner_web_top' => 'Veb — yuxarı',
        'banner_web_bottom' => 'Veb — aşağı',
        'banner_mobile_top' => 'Mobil — yuxarı',
        'banner_mobile_bottom' => 'Mobil — aşağı',
    ];
    // Tövsiyə olunan ölçülər — xana boşaldılanda placeholder kimi görünür
    $bannerPlaceholders = [
        'banner_web_top' => ['width' => 1060, 'height' => 320],
        'banner_web_bottom' => ['width' => 1060, 'height' => 220],
        'banner_mobile_top' => ['width' => 640, 'height' => 280],
        'banner_mobile_bottom' => ['width' => 640, 'height' => 220],
    ];
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
                            <tr>
                                <th scope="row" class="fw-medium text-nowrap pe-3">{{ $label }}</th>
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
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-3">Ölçü dəyişəndə artıq yüklənmiş bannerlər yenidən kəsilmir — yalnız yeni yüklənənlərə tətbiq olunur.</div>
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

                <div class="text-muted small mb-3">Nisbət önizləməsi (dizayner üçün)</div>
                @foreach ($bannerPlaces as $key => $label)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $label }}</span>
                            <span class="text-muted" data-banner-label="{{ $key }}">{{ $bannerSizes[$key]['width'] }}×{{ $bannerSizes[$key]['height'] }}</span>
                        </div>
                        <div class="settings-banner-preview {{ str_contains($key, 'mobile') ? 'is-mobile' : '' }}"
                             data-banner-preview="{{ $key }}"
                             style="aspect-ratio: {{ max(1, (int) $bannerSizes[$key]['width']) }} / {{ max(1, (int) $bannerSizes[$key]['height']) }}"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
