@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar', ['pageTitle' => __('credit_title'),])

                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('credit_title') }}</h1>
                    <p class="account-panel-desc">{{ __('credit_description') }}</p>

                    <form id="creditProfileForm" class="credit-form" novalidate method="POST" action="{{ route('profile.credit.update') }}"
                          enctype="multipart/form-data"
                          data-change-label="{{ __('credit_change') }}"
                          data-error-message="{{ __('credit_generic_error') }}">
                        @csrf

                        <div class="credit-grid">
                            <div class="credit-field">
                                <label for="father_name">{{ __('credit_father_name') }} *</label>
                                <input id="father_name" name="father_name" @class(['is-invalid' => $errors->has('father_name')]) aria-describedby="father_name-error" aria-invalid="{{ $errors->has('father_name') ? 'true' : 'false' }}" type="text" value="{{ old('father_name', $profile?->father_name) }}" required>
                                <div class="invalid-feedback" id="father_name-error" data-error="father_name">{{ $errors->first('father_name') }}</div>
                            </div>

                            <div class="credit-field">
                                <label for="fin">{{ __('credit_fin') }} *</label>
                                <input id="fin" name="fin" @class(['is-invalid' => $errors->has('fin')]) aria-describedby="fin-error" aria-invalid="{{ $errors->has('fin') ? 'true' : 'false' }}" type="text" value="{{ old('fin', $profile?->fin) }}" required>
                                <div class="invalid-feedback" id="fin-error" data-error="fin">{{ $errors->first('fin') }}</div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_1_name">{{ __('credit_relative_1_name') }} *</label>
                                <input id="relative_1_name" name="relative_1_name" @class(['is-invalid' => $errors->has('relative_1_name')]) aria-describedby="relative_1_name-error" aria-invalid="{{ $errors->has('relative_1_name') ? 'true' : 'false' }}" type="text" value="{{ old('relative_1_name', $profile?->relative_1_name) }}" required>
                                <div class="invalid-feedback" id="relative_1_name-error" data-error="relative_1_name">{{ $errors->first('relative_1_name') }}</div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_1_phone">{{ __('credit_relative_1_phone') }} *</label>
                                <input id="relative_1_phone" name="relative_1_phone" @class(['is-invalid' => $errors->has('relative_1_phone')]) aria-describedby="relative_1_phone-error" aria-invalid="{{ $errors->has('relative_1_phone') ? 'true' : 'false' }}" type="tel" value="{{ old('relative_1_phone', $profile?->relative_1_phone) }}" required>
                                <div class="invalid-feedback" id="relative_1_phone-error" data-error="relative_1_phone">{{ $errors->first('relative_1_phone') }}</div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_2_name">{{ __('credit_relative_2_name') }} *</label>
                                <input id="relative_2_name" name="relative_2_name" @class(['is-invalid' => $errors->has('relative_2_name')]) aria-describedby="relative_2_name-error" aria-invalid="{{ $errors->has('relative_2_name') ? 'true' : 'false' }}" type="text" value="{{ old('relative_2_name', $profile?->relative_2_name) }}" required>
                                <div class="invalid-feedback" id="relative_2_name-error" data-error="relative_2_name">{{ $errors->first('relative_2_name') }}</div>
                            </div>

                            <div class="credit-field">
                                <label for="relative_2_phone">{{ __('credit_relative_2_phone') }} *</label>
                                <input id="relative_2_phone" name="relative_2_phone" @class(['is-invalid' => $errors->has('relative_2_phone')]) aria-describedby="relative_2_phone-error" aria-invalid="{{ $errors->has('relative_2_phone') ? 'true' : 'false' }}" type="tel" value="{{ old('relative_2_phone', $profile?->relative_2_phone) }}" required>
                                <div class="invalid-feedback" id="relative_2_phone-error" data-error="relative_2_phone">{{ $errors->first('relative_2_phone') }}</div>
                            </div>

                            <div class="credit-work-row">
                                <div class="credit-field">
                                    <label for="workplace_name">{{ __('credit_workplace_name') }} *</label>
                                    <input id="workplace_name" name="workplace_name" @class(['is-invalid' => $errors->has('workplace_name')]) aria-describedby="workplace_name-error" aria-invalid="{{ $errors->has('workplace_name') ? 'true' : 'false' }}" type="text" value="{{ old('workplace_name', $profile?->workplace_name) }}" required>
                                    <div class="invalid-feedback" id="workplace_name-error" data-error="workplace_name">{{ $errors->first('workplace_name') }}</div>
                                </div>

                                <div class="credit-field">
                                    <label for="salary">{{ __('credit_salary') }} *</label>
                                    <input id="salary" name="salary" @class(['is-invalid' => $errors->has('salary')]) aria-describedby="salary-error" aria-invalid="{{ $errors->has('salary') ? 'true' : 'false' }}" type="number" step="0.01" min="0.01" value="{{ old('salary', $profile?->salary) }}" required>
                                    <div class="invalid-feedback" id="salary-error" data-error="salary">{{ $errors->first('salary') }}</div>
                                </div>

                                <div class="credit-field">
                                    <label for="position">{{ __('credit_position') }} *</label>
                                    <input id="position" name="position" @class(['is-invalid' => $errors->has('position')]) aria-describedby="position-error" aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}" type="text" value="{{ old('position', $profile?->position) }}" required>
                                    <div class="invalid-feedback" id="position-error" data-error="position">{{ $errors->first('position') }}</div>
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

                                            <input @class(['credit-file', 'is-invalid' => $errors->has($field)])
                                                   id="{{ $field }}"
                                                   name="{{ $field }}"
                                                   aria-describedby="{{ $field }}-error"
                                                   @class(['is-invalid' => $errors->has($field)])
                                                   aria-describedby="{{ $field }}-error"
                                                   type="file"
                                                   accept="image/jpeg,image/png,image/webp"
                                                   @if (!$hasImage) required @endif>

                                        </div>
                                        <div class="invalid-feedback" id="{{ $field }}-error" data-error="{{ $field }}">{{ $errors->first($field) }}</div>
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
