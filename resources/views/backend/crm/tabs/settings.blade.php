<div class="p-3">
    <div class="row g-4">
        <div class="col-md-6">
            <h5 class="mb-3">Müştəri məlumatları</h5>
            <dl class="row mb-0">
                <dt class="col-sm-4">Ad soyad</dt>
                <dd class="col-sm-8">{{ trim($customer->name . ' ' . $customer->surname) }}</dd>
                <dt class="col-sm-4">Telefon</dt>
                <dd class="col-sm-8">{{ $customer->mobile ?: '—' }}</dd>
                <dt class="col-sm-4">E-poçt</dt>
                <dd class="col-sm-8">{{ $customer->email ?: '—' }}</dd>
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">{{ $customer->active ? 'Aktiv' : 'Deaktiv' }}</dd>
                <dt class="col-sm-4">Qeydiyyat</dt>
                <dd class="col-sm-8">{{ $customer->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </dl>
        </div>
        <div class="col-md-6">
            <h5 class="mb-3">Ünvanlar</h5>
            @forelse($customer->addresses as $address)
                <div class="border rounded p-3 mb-2">
                    <div class="fw-semibold">{{ $address->title ?: 'Ünvan' }} @if($address->is_default)<span class="badge bg-primary ms-1">Əsas</span>@endif</div>
                    <div class="text-muted">{{ $address->label }}</div>
                </div>
            @empty
                <div class="text-muted">Qeyd edilən ünvan yoxdur.</div>
            @endforelse
        </div>
    </div>
</div>
