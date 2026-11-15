{{-- İstehsal → xammal: qalıq, orta maya, hərəkətlər (alış / istehsala sərf), adın düzəlişi --}}
@use('App\Models\Production\RawMaterial')
@use('App\Services\Production\OwnProduction')
@php
    $title = $material->name;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.production.materials') => 'Xammal', '#' => $title];
    $locked = $material->movements()->exists();
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="row g-3 mb-5">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <div class="text-muted text-small">Qalıq</div>
                <div class="cta-3 {{ $stock <= 0 ? 'text-danger' : 'text-primary' }} mb-3">{{ OwnProduction::qty($stock) }} {{ $material->unitLabel() }}</div>
                <div class="text-muted text-small">Orta maya (1 {{ $material->unitLabel() }})</div>
                <div class="mb-3">{{ number_format((float) $material->avg_cost, 4) }} ₼</div>
                <div class="text-muted text-small">Dəyəri</div>
                <div>{{ number_format(max(0, $stock) * (float) $material->avg_cost, 2) }} ₼</div>
            </div></div>
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('admin.production.material.update', $material) }}" data-once>
                    @csrf
                    <div class="mb-3"><label class="form-label" for="mName">Ad</label>
                        <input type="text" id="mName" name="name" class="form-control" maxlength="150" value="{{ old('name', $material->name) }}" required></div>
                    <div class="mb-3"><label class="form-label" for="mUnit">Vahid</label>
                        <select id="mUnit" name="unit" class="form-select" @if($locked) disabled @endif>
                            @foreach(RawMaterial::UNITS as $code => $label)<option value="{{ $code }}" @selected(old('unit', $material->unit) === $code)>{{ $label }}</option>@endforeach
                        </select>
                        @if($locked)<input type="hidden" name="unit" value="{{ $material->unit }}"><div class="form-text">Hərəkəti olan xammalın vahidi dəyişdirilmir.</div>@endif</div>
                    <div class="mb-3"><label class="form-label" for="mNote">Qeyd</label>
                        <input type="text" id="mNote" name="note" class="form-control" maxlength="500" value="{{ old('note', $material->note) }}"></div>
                    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="mActive" name="active" value="1" @checked($material->active)>
                        <label class="form-check-label" for="mActive">Aktiv (alışda və reseptdə seçilir)</label></div>
                    <button class="btn btn-primary">Yenilə</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            <h2 class="small-title">Hərəkətlər</h2>
            <div class="card"><div class="card-body">
                @if($movements->isEmpty())
                    <div class="text-muted text-center py-4">Hələ hərəkət yoxdur. Alışı "Xammal alışları"nda yazın.</div>
                @else
                    <div class="table-responsive"><table class="table mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Növ</th>
                            <th class="text-muted text-small text-uppercase text-end">Miqdar</th>
                            <th class="text-muted text-small text-uppercase text-end">Vahid</th>
                            <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                        </tr></thead>
                        <tbody>
                        @foreach($movements as $mv)
                            <tr>
                                <td>{{ $mv->occurred_at->format('d.m.Y') }}<div class="text-alternate text-small">{{ trim((string) $mv->user?->full_name) ?: '—' }}</div></td>
                                <td>
                                    @if($mv->purchase)
                                        <a href="{{ route('admin.production.purchase', $mv->purchase) }}">Alış #{{ $mv->purchase->id }}</a>
                                        <div class="text-alternate text-small">{{ $mv->purchase->supplier?->name ?? \App\Models\Production\MaterialPurchase::PAYMENTS[$mv->purchase->payment] }}</div>
                                    @elseif($mv->batch)
                                        <a href="{{ route('admin.production.product', $mv->batch->own_product_id) }}">İstehsal</a>
                                        <div class="text-alternate text-small">{{ $mv->batch->ownProduct?->label() }} × {{ $mv->batch->quantity }}</div>
                                    @else {{ \App\Models\Production\RawMaterialMovement::KINDS[$mv->kind] ?? $mv->kind }} @endif
                                </td>
                                <td class="text-end {{ (float) $mv->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ (float) $mv->quantity > 0 ? '+' : '' }}{{ OwnProduction::qty((float) $mv->quantity) }} {{ $material->unitLabel() }}</td>
                                <td class="text-end">{{ number_format((float) $mv->unit_cost, 4) }} ₼</td>
                                <td class="text-end">{{ number_format(abs((float) $mv->quantity * (float) $mv->unit_cost), 2) }} ₼</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="mt-3">{{ $movements->links() }}</div>
                @endif
            </div></div>
        </div>
    </div>
</div>
@endsection
