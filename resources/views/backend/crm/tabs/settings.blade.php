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
                <label class="form-label" for="customer-mobile-2">Ehtiyat telefon</label>
                <input id="customer-mobile-2" @class(['form-control', 'is-invalid' => $errors->has('mobile_2')]) name="mobile_2"
                       value="{{ old('mobile_2', $customer->mobile_2) }}" inputmode="numeric" maxlength="12" placeholder="994XXXXXXXXX">
                @error('mobile_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Könüllü — əsas nömrəyə çatmayanda (məs. ailə üzvü)</div>
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
            @php
                $details = collect(['Bina' => $address->building, 'Blok' => $address->entrance, 'Mərtəbə' => $address->floor, 'Mənzil' => $address->apartment])
                    ->filter()->map(fn ($v, $k) => $k.' '.$v)->implode(', ');
                // @json arqumenti vergülə görə bölür — massiv əvvəlcə dəyişənə yığılır
                $addressData = ['title' => $address->title, 'city_id' => $address->city_id, 'address' => $address->address,
                    'building' => $address->building, 'entrance' => $address->entrance, 'floor' => $address->floor,
                    'apartment' => $address->apartment, 'address_note' => $address->note, 'is_default' => (bool) $address->is_default];
            @endphp
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 d-flex gap-2">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $address->title ?: 'Ünvan' }} @if($address->is_default)<span class="badge bg-primary ms-1">Əsas</span>@endif</div>
                        <div class="text-muted mt-1">{{ $address->city }}, {{ $address->address }}</div>
                        @if($details)<div class="text-muted">{{ $details }}</div>@endif
                        @if($address->note)<div class="text-muted fst-italic">{{ $address->note }}</div>@endif
                    </div>
                    {{-- qələm → redaktə pəncərəsi (aşağıdakı #crmAddressModal) --}}
                    <button type="button" class="btn btn-sm btn-icon btn-icon-only btn-outline-primary align-self-start" title="Ünvanı redaktə et"
                            data-address-edit="{{ route('admin.crm.address.update', [$customer, $address]) }}"
                            data-address='@json($addressData)'>
                        <i data-acorn-icon="edit-square" data-acorn-size="16"></i>
                    </button>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">Qeyd edilən ünvan yoxdur.</div>
        @endforelse
    </div>

    {{-- Ünvanın redaktəsi: qələm → pəncərə; AJAX ilə yadda saxlanır, tab yenilənir --}}
    <div class="modal fade" id="crmAddressModal" tabindex="-1" aria-labelledby="crmAddressModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="crmAddressForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="crmAddressModalTitle">Ünvanı redaktə et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label" for="addr-title">Ünvanın adı</label>
                            <input class="form-control" id="addr-title" name="title" maxlength="50" placeholder="Ev, İş…">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="addr-city">Şəhər *</label>
                            <select class="form-select" id="addr-city" name="city_id" required>
                                <option value="">Seçin</option>
                                @foreach($cities as $city)<option value="{{ $city->id }}">{{ $city->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="addr-address">Küçə və ünvan *</label>
                            <input class="form-control" id="addr-address" name="address" maxlength="500" required>
                        </div>
                        <div class="col-6 col-md-3"><label class="form-label" for="addr-building">Bina</label><input class="form-control" id="addr-building" name="building" maxlength="50"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="addr-entrance">Blok</label><input class="form-control" id="addr-entrance" name="entrance" maxlength="50"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="addr-floor">Mərtəbə</label><input class="form-control" id="addr-floor" name="floor" maxlength="30"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="addr-apartment">Mənzil</label><input class="form-control" id="addr-apartment" name="apartment" maxlength="30"></div>
                        <div class="col-12">
                            <label class="form-label" for="addr-note">Əlavə məlumat</label>
                            <textarea class="form-control" id="addr-note" name="address_note" rows="2" maxlength="1000" placeholder="Kuryer üçün qeyd, orientir…"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="addr-default" name="is_default" value="1">
                                <label class="form-check-label" for="addr-default">Əsas ünvan</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger mt-3 mb-0" data-address-error hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Ləğv et</button>
                    <button type="submit" class="btn btn-primary">Yadda saxla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Tab hər yüklənəndə bu skript yenidən işləyir — hadisələr namespace ilə bir dəfə bağlanır
    (() => {
        const modalEl = document.getElementById('crmAddressModal');
        const form = document.getElementById('crmAddressForm');
        if (!modalEl || !form) return;
        // pəncərə body-yə köçürülür: tab yenilənəndə (#tabContent.html) itməsin, backdrop düzgün işləsin
        document.querySelectorAll('body > #crmAddressModal').forEach((old) => { if (old !== modalEl) old.remove(); });
        document.body.appendChild(modalEl);
        // admin-də Bootstrap 5.0.1 — getOrCreateInstance yoxdur
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        const error = form.querySelector('[data-address-error]');
        let url = null;

        $(document).off('click.addrEdit').on('click.addrEdit', '[data-address-edit]', function () {
            const data = JSON.parse(this.dataset.address || '{}');
            url = this.dataset.addressEdit;
            form.reset();
            error.hidden = true;
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            ['title', 'city_id', 'address', 'building', 'entrance', 'floor', 'apartment', 'address_note'].forEach((name) => {
                form.elements[name].value = data[name] ?? '';
            });
            form.elements.is_default.checked = Boolean(data.is_default);
            modal.show();
        });

        form.onsubmit = async (event) => {
            event.preventDefault();
            const button = form.querySelector('[type="submit"]');
            button.disabled = true;
            error.hidden = true;
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            try {
                const body = Object.fromEntries(new FormData(form));
                body.is_default = form.elements.is_default.checked ? 1 : 0;
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(body),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    Object.keys(json.errors || {}).forEach((name) => form.elements[name]?.classList.add('is-invalid'));
                    throw new Error(Object.values(json.errors || {})[0]?.[0] || json.message || 'Yadda saxlanmadı');
                }
                modalEl.addEventListener('hidden.bs.modal', () => {
                    // dəyişiklik görünsün — Tənzimləmələr tabı yenidən yüklənir
                    loadTab(ajax_url.customerTab.replace(':id', customerId).replace(':tab', 'settings'));
                }, { once: true });
                modal.hide();
            } catch (e) {
                error.textContent = e.message;
                error.hidden = false;
            } finally {
                button.disabled = false;
            }
        };
    })();
</script>

