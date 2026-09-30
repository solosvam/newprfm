<div class="p-3">
    <form method="POST" action="{{ route('admin.crm.credit-profile.update', $customer) }}" enctype="multipart/form-data">
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
            @foreach(['id_card_front' => 'Şəxsiyyət vəsiqəsi — ön', 'id_card_back' => 'Şəxsiyyət vəsiqəsi — arxa (yalnız AZE üçün)'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    @if($profile?->{$field})
                        <a href="{{ asset('frontend/uploads/customers/' . basename($profile->{$field})) }}" target="_blank" class="d-block mb-2">
                            <img src="{{ asset('frontend/uploads/customers/' . basename($profile->{$field})) }}" class="img-fluid rounded border" style="max-height:140px;" alt="{{ $label }}">
                        </a>
                    @endif
                    <input class="form-control" type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp" @required($field === 'id_card_front' && !$profile?->{$field})>
                </div>
            @endforeach
            <div class="col-12"><button type="submit" class="btn btn-primary">Dəyişiklikləri yadda saxla</button></div>
        </div>
    </form>
</div>
