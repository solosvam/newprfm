@php($horizontal = $horizontal ?? false)
@foreach([
    'name_az' => ['Anbarın adı', 150],
    'contact_name' => ['Əlaqəli şəxs', 150],
    'phone' => ['Telefon', 30],
    'telegram_id' => ['Telegram ID', 21],
    'address' => ['Ünvan', 500],
] as $field => [$label, $maxlength])
    <div class="mb-3 {{ $horizontal ? 'row' : '' }}">
        <label for="warehouse_{{ $field }}" class="{{ $horizontal ? 'col-lg-2 col-md-3 col-sm-4 col-form-label' : 'form-label' }}">{{ $label }}</label>
        <div @class(['col-sm-8 col-md-9 col-lg-10' => $horizontal])>
            <input type="text" id="warehouse_{{ $field }}" name="{{ $field }}"
                   class="form-control @error($field) is-invalid @enderror"
                   value="{{ old($field, $warehouse?->{$field}) }}" maxlength="{{ $maxlength }}"
                   @required($field === 'name_az') @if($field === 'telegram_id') inputmode="numeric" placeholder="İstəyə bağlı, məs. 123456789" @endif>
            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
@endforeach
<div class="mb-3 {{ $horizontal ? 'row' : '' }}">
    <label for="warehouse_request_channel" class="{{ $horizontal ? 'col-lg-2 col-md-3 col-sm-4 col-form-label' : 'form-label' }}">Sorğu tipi</label>
    <div @class(['col-sm-8 col-md-9 col-lg-10' => $horizontal])>
        <select id="warehouse_request_channel" name="request_channel" class="form-select @error('request_channel') is-invalid @enderror">
            @foreach(\App\Models\Procurement\Warehouse::CHANNELS as $value => $label)
                <option value="{{ $value }}" @selected(old('request_channel', $warehouse?->request_channel ?? 'sms') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">Anbara sorğu bu yolla göndərilir. Telegram üçün Telegram ID yazılmalıdır.</div>
        @error('request_channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="mb-3 {{ $horizontal ? 'row' : '' }}">
    <label for="warehouse_active" class="{{ $horizontal ? 'col-lg-2 col-md-3 col-sm-4 col-form-label' : 'form-label' }}">Aktiv</label>
    <div @class(['col-sm-8 col-md-9 col-lg-10' => $horizontal])>
        <select id="warehouse_active" name="active" class="form-select @error('active') is-invalid @enderror">
            <option value="1" @selected((int) old('active', $warehouse?->active ?? 1) === 1)>Aktiv</option>
            <option value="0" @selected((int) old('active', $warehouse?->active ?? 1) === 0)>Deaktiv</option>
        </select>
        @error('active')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
