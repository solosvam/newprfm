@php($horizontal = $horizontal ?? false)
@foreach([
    'name_az' => ['Anbarın adı', 150],
    'contact_name' => ['Əlaqəli şəxs', 150],
    'phone' => ['Telefon', 30],
    'address' => ['Ünvan', 500],
] as $field => [$label, $maxlength])
    <div class="mb-3 {{ $horizontal ? 'row' : '' }}">
        <label for="warehouse_{{ $field }}" class="{{ $horizontal ? 'col-lg-2 col-md-3 col-sm-4 col-form-label' : 'form-label' }}">{{ $label }}</label>
        <div @class(['col-sm-8 col-md-9 col-lg-10' => $horizontal])>
            <input type="text" id="warehouse_{{ $field }}" name="{{ $field }}"
                   class="form-control @error($field) is-invalid @enderror"
                   value="{{ old($field, $warehouse?->{$field}) }}" maxlength="{{ $maxlength }}"
                   @required($field === 'name_az')>
            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
@endforeach
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
