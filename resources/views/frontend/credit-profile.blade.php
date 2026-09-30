@extends('frontend.layouts.app')

@use('App\Models\Customer\CustomerCreditProfile')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/vendor/cropper.min.css') }}">
@endsection

@section('content')
    @php
        $attrs = fn (array $a) => collect($a)->map(fn ($v, $k) => $k . '="' . e($v) . '"')->implode(' ');

        $sections = [
            'credit_section_personal' => ['grid' => 'credit-grid credit-grid--3', 'fields' => [
                ['father_name', __('credit_father_name'), 'text', ['autocomplete' => 'additional-name']],
                ['fin', __('credit_fin'), 'text', [
                    'maxlength' => 7, 'minlength' => 7, 'pattern' => '[A-Za-z0-9]{7}',
                    'autocapitalize' => 'characters', 'autocomplete' => 'off', 'spellcheck' => 'false',
                    'class' => 'is-upper', 'placeholder' => 'XXXXXXX',
                ]],
                // seriya (select) + nömrə — bir xanada
                ['id_card_number', __('credit_id_card'), 'id_card', [
                    'maxlength' => 8, 'autocapitalize' => 'characters', 'autocomplete' => 'off', 'spellcheck' => 'false',
                    'class' => 'is-upper', 'placeholder' => __('credit_id_card_number'),
                ]],
            ]],
            'credit_section_relatives' => ['grid' => 'credit-grid', 'hint' => __('credit_section_relatives_hint'), 'fields' => [
                ['relative_1_name', __('credit_relative_1_name'), 'text', ['autocomplete' => 'off']],
                ['relative_1_phone', __('credit_relative_1_phone'), 'tel', ['inputmode' => 'tel', 'autocomplete' => 'off', 'placeholder' => '050 000 00 00']],
                ['relative_2_name', __('credit_relative_2_name'), 'text', ['autocomplete' => 'off']],
                ['relative_2_phone', __('credit_relative_2_phone'), 'tel', ['inputmode' => 'tel', 'autocomplete' => 'off', 'placeholder' => '050 000 00 00']],
            ]],
            'credit_section_work' => ['grid' => 'credit-grid', 'fields' => [
                ['workplace_name', __('credit_workplace_name'), 'text', ['autocomplete' => 'organization']],
                ['salary', __('credit_salary'), 'number', ['step' => '0.01', 'min' => '0.01', 'inputmode' => 'decimal']],
            ]],
        ];
    @endphp

    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('credit_title')])

            <div class="account-panel account-panel--flush">
                <h1 class="account-panel-title">{{ __('credit_title') }}</h1>
                <p class="account-panel-desc">{{ __('credit_description') }}</p>

                <form id="creditProfileForm" class="credit-form" novalidate
                      method="POST" action="{{ route('profile.credit.update') }}" enctype="multipart/form-data"
                      data-return-url="{{ $returnUrl ?? '' }}"
                      data-change-label="{{ __('credit_change') }}"
                      data-select-label="{{ __('credit_select_image') }}"
                      data-error-message="{{ __('credit_generic_error') }}"
                      data-ocr-url="{{ route('profile.credit.ocr') }}"
                      data-ocr-reading="{{ __('credit_ocr_reading') }}"
                      data-ocr-done="{{ __('credit_ocr_done') }}"
                      data-ocr-nothing="{{ __('credit_ocr_nothing') }}"
                      data-ocr-not-card="{{ __('credit_ocr_not_card') }}"
                      data-ocr-name-mismatch="{{ __('credit_ocr_name_mismatch') }}">
                    @csrf

                    <section class="credit-section credit-section--documents">
                        <h2 class="credit-section__title">{{ __('credit_section_documents') }}</h2>
                        <p class="credit-section__hint">{{ __('credit_section_documents_hint') }}</p>

                        @php
                            // arxa üz yalnız köhnə vəsiqə (AZE) üçün — seriya dəyişəndə credit-profile.js göstərir/gizlədir
                            $needsBack = CustomerCreditProfile::needsBackSide(old('id_card_series', $profile?->id_card_series));
                        @endphp
                        <div class="credit-photos-row" data-double-side-series='@json(CustomerCreditProfile::DOUBLE_SIDE_SERIES)'>
                            @foreach (['id_card_front' => __('credit_id_card_front'), 'id_card_back' => __('credit_id_card_back')] as $field => $label)
                                @php
                                    $hasImage = (bool) $profile?->{$field};
                                    $isBack = $field === 'id_card_back';
                                @endphp

                                <div class="credit-field" @if($isBack) data-back-side @if(!$needsBack) hidden @endif @endif>
                                    <label for="{{ $field }}">{{ $label }} *</label>

                                    <div class="credit-photo" data-photo="{{ $field }}">
                                        <img class="credit-preview"
                                             src="{{ $hasImage ? asset('frontend/uploads/customers/' . basename($profile->{$field})) : '' }}"
                                             alt="{{ $label }}"
                                             @if (!$hasImage) hidden @endif>

                                        <span class="credit-empty" @if ($hasImage) hidden @endif>{{ __('credit_no_image') }}</span>

                                        <button type="button" class="credit-change">
                                            {{ $hasImage ? __('credit_change') : __('credit_select_image') }}
                                        </button>

                                        <input @class(['credit-file', 'is-invalid' => $errors->has($field)])
                                               id="{{ $field }}" name="{{ $field }}" type="file"
                                               accept="image/jpeg,image/png,image/webp"
                                               aria-describedby="{{ $field }}-error"
                                               @if (!$hasImage && (!$isBack || $needsBack)) required @endif>
                                    </div>
                                    <div class="invalid-feedback" id="{{ $field }}-error" data-error="{{ $field }}">{{ $errors->first($field) }}</div>
                                    @unless($isBack)
                                        {{-- OCR nəticəsi (credit-profile.js) --}}
                                        <div class="credit-ocr" data-ocr-status aria-live="polite" hidden></div>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @foreach($sections as $titleKey => $section)
                        <section class="credit-section">
                            <h2 class="credit-section__title">{{ __($titleKey) }}</h2>
                            @if(!empty($section['hint']))
                                <p class="credit-section__hint">{{ $section['hint'] }}</p>
                            @endif

                            <div class="{{ $section['grid'] }}">
                                @foreach($section['fields'] as [$name, $label, $type, $extra])
                                    @php
                                        $class = trim(($extra['class'] ?? '') . ($errors->has($name) ? ' is-invalid' : ''));
                                        unset($extra['class']);
                                    @endphp
                                    @if($type === 'id_card')
                                        @php $series = old('id_card_series', $profile?->id_card_series); @endphp
                                        <div class="credit-field">
                                            <label for="id_card_series">{{ $label }} *</label>
                                            <div class="credit-idcard">
                                                <select id="id_card_series" name="id_card_series" required
                                                        data-placeholder="{{ __('credit_id_card_series') }}"
                                                        aria-label="{{ __('credit_id_card_series') }}"
                                                        aria-describedby="id_card_series-error"
                                                        @class(['credit-series-select', 'is-invalid' => $errors->has('id_card_series')])>
                                                    <option value=""></option>
                                                    @foreach(CustomerCreditProfile::ID_CARD_SERIES as $option)
                                                        <option value="{{ $option }}" @selected($series === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                                <input id="{{ $name }}" name="{{ $name }}" type="text"
                                                       @if($class) class="{{ $class }}" @endif
                                                       value="{{ old($name, $profile?->{$name}) }}"
                                                       aria-label="{{ __('credit_id_card_number') }}"
                                                       aria-describedby="{{ $name }}-error"
                                                       aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                                                       required {!! $attrs($extra) !!}>
                                            </div>
                                            <div class="invalid-feedback" id="id_card_series-error" data-error="id_card_series">{{ $errors->first('id_card_series') }}</div>
                                            <div class="invalid-feedback" id="{{ $name }}-error" data-error="{{ $name }}">{{ $errors->first($name) }}</div>
                                        </div>
                                    @else
                                        <div class="credit-field">
                                            <label for="{{ $name }}">{{ $label }} *</label>
                                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
                                                   @if($class) class="{{ $class }}" @endif
                                                   value="{{ old($name, $profile?->{$name}) }}"
                                                   aria-describedby="{{ $name }}-error"
                                                   aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                                                   required {!! $attrs($extra) !!}>
                                            <div class="invalid-feedback" id="{{ $name }}-error" data-error="{{ $name }}">{{ $errors->first($name) }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <button type="submit" id="creditSubmit" class="btn btn-dark account-form-submit">{{ __('credit_save') }}</button>
                </form>
            </div>
        </div>
    </main>

    {{-- Vəsiqə şəklinin kəsilməsi (id-card-cropper.js) --}}
    <dialog id="idCardCropDialog" class="idcrop" aria-labelledby="idCropTitle"
            data-read-error="{{ __('credit_crop_read_error') }}">
        <div class="idcrop__head">
            <h2 id="idCropTitle" class="idcrop__title">{{ __('credit_crop_title') }}</h2>
            <button type="button" class="idcrop__close" data-crop-cancel aria-label="{{ __('credit_crop_cancel') }}">&times;</button>
        </div>
        <p class="idcrop__hint">{{ __('credit_crop_hint') }}</p>
        <div class="idcrop__stage"><img data-crop-image alt=""></div>
        <div class="idcrop__tools">
            <button type="button" data-crop-rotate="-90" aria-label="{{ __('credit_crop_rotate_left') }}" title="{{ __('credit_crop_rotate_left') }}">&#8634;</button>
            <button type="button" data-crop-rotate="90" aria-label="{{ __('credit_crop_rotate_right') }}" title="{{ __('credit_crop_rotate_right') }}">&#8635;</button>
            <button type="button" data-crop-zoom="0.1" aria-label="{{ __('credit_crop_zoom_in') }}" title="{{ __('credit_crop_zoom_in') }}">+</button>
            <button type="button" data-crop-zoom="-0.1" aria-label="{{ __('credit_crop_zoom_out') }}" title="{{ __('credit_crop_zoom_out') }}">&minus;</button>
        </div>
        <ul class="idcrop__checks" aria-live="polite">
            <li data-check="sharp" data-ok="{{ __('credit_crop_sharp_ok') }}" data-bad="{{ __('credit_crop_sharp_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
            <li data-check="size" data-ok="{{ __('credit_crop_size_ok') }}" data-bad="{{ __('credit_crop_size_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
            <li data-check="light" data-ok="{{ __('credit_crop_light_ok') }}" data-bad="{{ __('credit_crop_light_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
        </ul>
        <div class="idcrop__actions">
            <button type="button" class="btn btn-light" data-crop-cancel>{{ __('credit_crop_cancel') }}</button>
            <button type="button" class="btn btn-dark" data-crop-confirm
                    data-label="{{ __('credit_crop_confirm') }}" data-label-anyway="{{ __('credit_crop_confirm_anyway') }}">{{ __('credit_crop_confirm') }}</button>
        </div>
    </dialog>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/vendor/cropper.min.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/id-card-cropper.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/credit-profile.js') }}" defer></script>
@endsection
