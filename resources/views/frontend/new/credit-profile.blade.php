@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar', ['pageTitle' => __('credit_title'),])

                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('credit_title') }}</h1>
                    <p class="account-panel-desc">{{ __('credit_description') }}</p>

                    <form id="creditProfileForm" class="credit-form" method="POST" action="{{ route('profile.credit.update') }}"
                          enctype="multipart/form-data"
                          data-change-label="{{ __('credit_change') }}"
                          data-error-message="{{ __('credit_generic_error') }}">
                        @csrf

                        <div class="credit-grid">
                            <div class="credit-field">
                                <label for="father_name">{{ __('credit_father_name') }} *</label>
                                <input id="father_name" name="father_name" type="text" value="{{ old('father_name', $profile?->father_name) }}" required>
                                <div class="invalid-feedback" data-error="father_name"></div>
                            </div>

                            <div class="credit-field">
                                <label for="fin">{{ __('credit_fin') }} *</label>
                                <input id="fin" name="fin" type="text" value="{{ old('fin', $profile?->fin) }}" required>
                                <div class="invalid-feedback" data-error="fin"></div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_1_name">{{ __('credit_relative_1_name') }} *</label>
                                <input id="relative_1_name" name="relative_1_name" type="text" value="{{ old('relative_1_name', $profile?->relative_1_name) }}" required>
                                <div class="invalid-feedback" data-error="relative_1_name"></div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_1_phone">{{ __('credit_relative_1_phone') }} *</label>
                                <input id="relative_1_phone" name="relative_1_phone" type="tel" value="{{ old('relative_1_phone', $profile?->relative_1_phone) }}" required>
                                <div class="invalid-feedback" data-error="relative_1_phone"></div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_2_name">{{ __('credit_relative_2_name') }} *</label>
                                <input id="relative_2_name" name="relative_2_name" type="text" value="{{ old('relative_2_name', $profile?->relative_2_name) }}" required>
                                <div class="invalid-feedback" data-error="relative_2_name"></div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_2_phone">{{ __('credit_relative_2_phone') }} *</label>
                                <input id="relative_2_phone" name="relative_2_phone" type="tel" value="{{ old('relative_2_phone', $profile?->relative_2_phone) }}" required>
                                <div class="invalid-feedback" data-error="relative_2_phone"></div>
                            </div>

                            <div class="credit-work-row">
                                <div class="credit-field">
                                    <label for="workplace_name">{{ __('credit_workplace_name') }} *</label>
                                    <input id="workplace_name" name="workplace_name" type="text" value="{{ old('workplace_name', $profile?->workplace_name) }}" required>
                                    <div class="invalid-feedback" data-error="workplace_name"></div>
                                </div>

                                <div class="credit-field">
                                    <label for="salary">{{ __('credit_salary') }} *</label>
                                    <input id="salary" name="salary" type="number" step="0.01" min="0.01" value="{{ old('salary', $profile?->salary) }}" required>
                                    <div class="invalid-feedback" data-error="salary"></div>
                                </div>

                                <div class="credit-field">
                                    <label for="position">{{ __('credit_position') }} *</label>
                                    <input id="position" name="position" type="text" value="{{ old('position', $profile?->position) }}" required>
                                    <div class="invalid-feedback" data-error="position"></div>
                                </div>
                            </div>

                            <div class="credit-photos-row">
                                @foreach (['id_card_front' => __('credit_id_card_front'), 'id_card_back' => __('credit_id_card_back')] as $field => $label)
                                    @php
                                        $side = $field === 'id_card_front' ? 'front' : 'back';
                                        $hasImage = (bool) $profile?->{$field};
                                    @endphp

                                    <div class="credit-field">
                                        <label for="{{ $field }}">{{ $label }} *</label>

                                        <div class="credit-photo" data-photo="{{ $field }}">
                                            <img class="credit-preview"
                                                 src="{{ $hasImage ? route('profile.credit.image', ['side' => $side]) : '' }}"
                                                 alt="{{ $label }}"
                                                 @if (!$hasImage) hidden @endif>

                                            <span class="credit-empty" @if ($hasImage) hidden @endif>
                                                {{ __('credit_no_image') }}
                                            </span>

                                            <button type="button" class="credit-change">
                                                {{ $hasImage ? __('credit_change') : __('credit_select_image') }}
                                            </button>

                                            <input class="credit-file"
                                                   id="{{ $field }}"
                                                   name="{{ $field }}"
                                                   type="file"
                                                   accept="image/jpeg,image/png,image/webp"
                                                   @if (!$hasImage) required @endif>

                                            <div class="invalid-feedback" data-error="{{ $field }}"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" id="creditSubmit" class="btn btn-dark account-form-submit">{{ __('credit_save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset('frontend/js/credit-profile.js') }}"></script>
@endsection
