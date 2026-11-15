{{-- İstehsal → Hazır mallar: öz məhsullarımız (kataloqdakı ölçü), qalıq, orta maya, partiya gözləyən sifarişlər --}}
@php
    $title = 'Hazır mallar';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'İstehsal', '#' => $title];
    $low = \App\Services\Production\OwnProduction::LOW_STOCK;
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#ownProductModal">
                    <span>Yeni məhsul</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-5"><div class="card-body">
        @if($products->isEmpty())
            <div class="text-muted text-center py-5">Hələ öz məhsulumuz yoxdur. "Yeni məhsul" ilə kataloqdakı ətrin ölçüsünü seçin, sonra resepti yazıb partiya istehsal edin.</div>
        @else
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Məhsul</th>
                    <th class="text-muted text-small text-uppercase text-end">Stokda</th>
                    <th class="text-muted text-small text-uppercase text-end">Orta maya</th>
                    <th class="text-muted text-small text-uppercase text-end">İstehsal olunub</th>
                    <th class="text-muted text-small text-uppercase">Partiya gözləyir</th>
                </tr></thead>
                <tbody>
                @foreach($products as $own)
                    @php $s = $stocks[$own->id] ?? ['stock' => 0, 'produced' => 0]; $w = $waiting[$own->id] ?? null; @endphp
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.production.product', $own) }}'">
                        <td><a href="{{ route('admin.production.product', $own) }}">{{ $own->label() }}</a>
                            @unless($own->active)<span class="badge bg-outline-secondary ms-1">Deaktiv</span>@endunless</td>
                        <td class="text-end {{ $own->active && $s['stock'] <= $low ? 'text-danger fw-bold' : '' }}">{{ $s['stock'] }}</td>
                        <td class="text-end">{{ number_format((float) $own->avg_cost, 2) }} ₼</td>
                        <td class="text-end text-alternate">{{ $s['produced'] }}</td>
                        <td>@if($w)<span class="text-danger">{{ $w['orders'] }} sifariş · {{ $w['quantity'] }} ədəd</span>@else<span class="text-alternate">—</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div></div>
</div>

{{-- Yeni məhsul: kataloqda brend / ad ilə axtarış, ölçü seçilir --}}
<div class="modal modal-right fade" id="ownProductModal" tabindex="-1" aria-labelledby="ownProductTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.production.products.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="ownProductTitle">Yeni məhsul</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <label class="form-label" for="ownProductSearch">Kataloqda axtar</label>
            <input type="search" id="ownProductSearch" class="form-control" placeholder="Brend və ya ad" autocomplete="off">
            <input type="hidden" name="product_variant_id" id="ownProductVariant" value="{{ old('product_variant_id') }}">
            <div class="list-group mt-2" id="ownProductResults"></div>
            <div class="form-text mt-3">Ətir əvvəlcə kataloqda (Məhsullar) olmalıdır — saytda adi məhsul kimi satılır. Hər ölçü ayrıca öz məhsulumuzdur.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary" id="ownProductSubmit" disabled>Əlavə et</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
<script>
    (() => {
        const input = document.getElementById('ownProductSearch'), box = document.getElementById('ownProductResults');
        const hidden = document.getElementById('ownProductVariant'), submit = document.getElementById('ownProductSubmit');
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            hidden.value = ''; submit.disabled = true;
            timer = setTimeout(async () => {
                const q = input.value.trim();
                box.innerHTML = '';
                if (q.length < 2) return;
                const rows = await fetch('{{ route('admin.production.variants') }}?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then((r) => r.json());
                if (!rows.length) { box.innerHTML = '<div class="text-muted text-small p-2">Tapılmadı</div>'; return; }
                rows.forEach((row) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action' + (row.taken ? ' disabled' : '');
                    b.textContent = row.label + (row.taken ? ' — artıq var' : '');
                    b.addEventListener('click', () => {
                        box.querySelectorAll('.active').forEach((el) => el.classList.remove('active'));
                        b.classList.add('active');
                        hidden.value = row.id; submit.disabled = false;
                    });
                    box.appendChild(b);
                });
            }, 250);
        });
        @if($errors->any())
            new bootstrap.Modal(document.getElementById('ownProductModal')).show();
        @endif
    })();
</script>
@endsection
