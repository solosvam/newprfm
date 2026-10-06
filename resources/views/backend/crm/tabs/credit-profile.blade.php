<div class="p-3">
    <form id="crmCreditProfileForm"
          data-ocr-url="{{ route('admin.crm.credit-profile.ocr', $customer) }}"
          data-ocr-reading="{{ __('credit_ocr_reading') }}"
          data-ocr-done="{{ __('credit_ocr_done') }}"
          data-ocr-nothing="{{ __('credit_ocr_nothing') }}"
          data-ocr-not-card="{{ __('credit_ocr_not_card') }}"
          data-ocr-name-mismatch="{{ __('credit_ocr_name_mismatch') }}"
          data-error-message="{{ __('credit_generic_error') }}"
          data-double-side-series='@json(\App\Models\Customer\CustomerCreditProfile::DOUBLE_SIDE_SERIES)'
          method="POST" action="{{ route('admin.crm.credit-profile.update', $customer) }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Ata adı</label><input class="form-control" name="father_name" value="{{ old('father_name', $profile?->father_name) }}" required></div>
            <div class="col-md-3"><label class="form-label">FİN</label><input class="form-control text-uppercase" name="fin" maxlength="7" value="{{ old('fin', $profile?->fin) }}" required></div>
            @php $series = old('id_card_series', $profile?->id_card_series); @endphp
            <div class="col-md-3">
                <label class="form-label" for="crm-id-card-number">Vəsiqə (seriya, nömrə)</label>
                <div class="input-group">
                    <select class="form-select flex-grow-0 w-auto" name="id_card_series" aria-label="Seriya" required>
                        <option value="" disabled @selected(!$series)>—</option>
                        @foreach(\App\Models\Customer\CustomerCreditProfile::ID_CARD_SERIES as $option)
                            <option value="{{ $option }}" @selected($series === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <input class="form-control text-uppercase" id="crm-id-card-number" name="id_card_number" maxlength="8" value="{{ old('id_card_number', $profile?->id_card_number) }}" required>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">Qohum ad</label><input class="form-control" name="relative_1_name" value="{{ old('relative_1_name', $profile?->relative_1_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Qohum mobil</label><input class="form-control" name="relative_1_phone" value="{{ old('relative_1_phone', $profile?->relative_1_phone) }}" required></div>
            <div class="col-md-6"><label class="form-label">Qohum ad</label><input class="form-control" name="relative_2_name" value="{{ old('relative_2_name', $profile?->relative_2_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Qohum mobil</label><input class="form-control" name="relative_2_phone" value="{{ old('relative_2_phone', $profile?->relative_2_phone) }}" required></div>
            <div class="col-md-6"><label class="form-label">İş yeri</label><input class="form-control" name="workplace_name" value="{{ old('workplace_name', $profile?->workplace_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Əmək haqqı</label><input class="form-control" type="number" step="0.01" min="0.01" name="salary" value="{{ old('salary', $profile?->salary) }}" required></div>
            <div class="col-12"><p class="text-muted mb-0">{{ __('credit_section_documents_hint') }}</p></div>
            @php $needsBack = \App\Models\Customer\CustomerCreditProfile::needsBackSide($series); @endphp
            @foreach(['id_card_front' => 'Şəxsiyyət vəsiqəsi — ön', 'id_card_back' => 'Şəxsiyyət vəsiqəsi — arxa (yalnız AZE üçün)'] as $field => $label)
                @php
                    $hasImage = (bool) $profile?->{$field};
                    $canView = $hasImage && auth('admin')->user()?->can('crm.id_card'); // şəkil var, amma icazəsiz göstərilmir
                    $isBack = $field === 'id_card_back';
                @endphp
                <div class="col-md-6" @if($isBack) data-back-side @if(!$needsBack) hidden @endif @endif>
                    <label class="form-label" for="crm-{{ $field }}">{{ $label }} *</label>
                    <div data-photo="{{ $field }}">
                        <img src="{{ $canView ? route('admin.crm.id-card', ['customer' => $customer, 'side' => str_replace('id_card_', '', $field), 'v' => \App\Services\IdCard\IdCardStorage::version($profile->{$field})]) : '' }}"
                             class="crm-credit-preview img-fluid rounded border d-block mb-2" alt="{{ $label }}"
                             @if(!$canView) hidden @endif>
                        @if($hasImage && !$canView)
                            @include('backend.crm.partials.id-card-locked')
                        @endif
                        <input @class(['form-control', 'is-invalid' => $errors->has($field)]) id="crm-{{ $field }}"
                               type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp"
                               @required(!$hasImage && (!$isBack || $needsBack))>
                        <div class="invalid-feedback" data-error="{{ $field }}">{{ $errors->first($field) }}</div>
                    </div>
                    @unless($isBack)
                        <div class="alert mt-2 mb-0" data-ocr-status aria-live="polite" hidden></div>
                    @endunless
                </div>
            @endforeach
            <div class="col-12"><button type="submit" class="btn btn-primary">Dəyişiklikləri yadda saxla</button></div>
        </div>
    </form>
</div>
