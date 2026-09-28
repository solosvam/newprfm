<div class="p-3">
    <form method="POST" action="{{ route('admin.crm.update', $customer) }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="customer-name">Ad</label>
                <input id="customer-name" class="form-control" name="name" value="{{ old('name', $customer->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-surname">Soyad</label>
                <input id="customer-surname" class="form-control" name="surname" value="{{ old('surname', $customer->surname) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-mobile">Telefon</label>
                <input id="customer-mobile" class="form-control" name="mobile" value="{{ old('mobile', $customer->mobile) }}" inputmode="numeric" maxlength="12" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-email">E-poçt</label>
                <input id="customer-email" class="form-control" name="email" value="{{ old('email', $customer->email) }}" type="email">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer-gender">Cinsiyyət</label>
                <select id="customer-gender" class="form-select" name="gender" required>
                    <option value="1" @selected((string) old('gender', $customer->gender) === '1')>Kişi</option>
                    <option value="0" @selected((string) old('gender', $customer->gender) === '0')>Qadın</option>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="border rounded w-100 px-3 d-flex align-items-center" style="height: 48px;">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" name="active" value="1" type="checkbox" id="customer-active" @checked(old('active', $customer->active))>
                        <label class="form-check-label" for="customer-active">Müştəri aktivdir</label>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Dəyişiklikləri yadda saxla</button>
            </div>
        </div>
    </form>

    <hr class="my-4">
    <h5 class="mb-3">Ünvanlar</h5>
    <div class="row g-3">
        @forelse($customer->addresses as $address)
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold">{{ $address->title ?: 'Ünvan' }} @if($address->is_default)<span class="badge bg-primary ms-1">Əsas</span>@endif</div>
                    <div class="text-muted mt-1">{{ $address->label }}</div>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">Qeyd edilən ünvan yoxdur.</div>
        @endforelse
    </div>
</div>
