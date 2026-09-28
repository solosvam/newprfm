@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
@endsection

@section('content')
    @php
        $attrs = fn (array $a) => collect($a)->map(fn ($v, $k) => $k . '="' . e($v) . '"')->implode(' ');

        $sections = [
            'credit_section_personal' => ['grid' => 'credit-grid', 'fields' => [
                ['father_name', __('credit_father_name'), 'text', ['autocomplete' => 'additional-name']],
                ['fin', __('credit_fin'), 'text', [
                    'maxlength' => 7, 'minlength' => 7, 'pattern' => '[A-Za-z0-9]{7}',
                    'autocapitalize' => 'characters', 'autocomplete' => 'off', 'spellcheck' => 'false',
                    'class' => 'is-upper', 'placeholder' => 'XXXXXXX',
                ]],
            ]],
            'credit_section_relatives' => ['grid' => 'credit-grid', 'hint' => __('credit_section_relatives_hint'), 'fields' => [
                ['relative_1_name', __('credit_relative_1_name'), 'text', ['autocomplete' => 'off']],
                ['relative_1_phone', __('credit_relative_1_phone'), 'tel', ['inputmode' => 'tel', 'autocomplete' => 'off', 'placeholder' => '050 000 00 00']],
                ['relative_2_name', __('credit_relative_2_name'), 'text', ['autocomplete' => 'off']],
                ['relative_2_phone', __('credit_relative_2_phone'), 'tel', ['inputmode' => 'tel', 'autocomplete' => 'off', 'placeholder' => '050 000 00 00']],
            ]],
            'credit_section_work' => ['grid' => 'credit-grid credit-grid--3', 'fields' => [
                ['workplace_name', __('credit_workplace_name'), 'text', ['autocomplete' => 'organization']],
                ['position', __('credit_position'), 'text', ['autocomplete' => 'organization-title']],
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
                      data-error-message="{{ __('credit_generic_error') }}">
                    @csrf

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
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <section class="credit-section">
                        <h2 class="credit-section__title">{{ __('credit_section_documents') }}</h2>
                        <p class="credit-section__hint">{{ __('credit_section_documents_hint') }}</p>

                        <div class="credit-photos-row">
                            @foreach (['id_card_front' => __('credit_id_card_front'), 'id_card_back' => __('credit_id_card_back')] as $field => $label)
                                @php $hasImage = (bool) $profile?->{$field}; @endphp

                                <div class="credit-field">
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
                                               @if (!$hasImage) required @endif>
                                    </div>
                                    <div class="invalid-feedback" id="{{ $field }}-error" data-error="{{ $field }}">{{ $errors->first($field) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <button type="submit" id="creditSubmit" class="btn btn-dark account-form-submit">{{ __('credit_save') }}</button>
                </form>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/credit-profile.js') }}" defer></script>
@endsection
