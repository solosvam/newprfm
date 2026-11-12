@php($horizontal = $horizontal ?? false)
@foreach([
    'name_az' => ['Deponun adı', 150],
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
                   @required($field === 'name_az') @if($field === 'telegram_id') inputmode="numeric" placeholder="Qrupun ID-si, məs. 482788956" @endif>
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
        <div class="form-text">Depoya sorğu bu yolla göndərilir. Telegram üçün deponun qrupunun ID-si yazılır ("-" işarəsi göndərəndə özü əlavə olunur). Qrupun ID-sini öyrənmək üçün qrupda /id yazın.</div>
        @error('request_channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
@can('finance')
    {{-- Köhnə hesabat proqramındakı borc: depo borcuna əlavə olunur (Maliyyə → depo). Mənfi — depo bizə borcludur --}}
    <div class="mb-3 {{ $horizontal ? 'row' : '' }}">
        <label for="warehouse_opening_debt" class="{{ $horizontal ? 'col-lg-2 col-md-3 col-sm-4 col-form-label' : 'form-label' }}">Başlanğıc borc, AZN</label>
        <div @class(['col-sm-8 col-md-9 col-lg-10' => $horizontal])>
            <input type="number" step="0.01" id="warehouse_opening_debt" name="opening_debt" inputmode="decimal"
                   class="form-control @error('opening_debt') is-invalid @enderror"
                   value="{{ old('opening_debt', $warehouse && (float) $warehouse->opening_debt != 0 ? number_format((float) $warehouse->opening_debt, 2, '.', '') : '') }}" placeholder="0.00">
            <div class="form-text">Köhnə hesabat proqramındakı borcumuz. Depo borcuna əlavə olunur, kassaya təsir etmir. Depo bizə borcludursa — mənfi yazın.</div>
            @error('opening_debt')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
@endcan
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
