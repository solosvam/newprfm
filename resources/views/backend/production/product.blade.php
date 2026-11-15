{{-- İstehsal → öz məhsulumuz: qalıq, orta maya, resept (bir şüşəyə), partiyalar və "Yeni partiya" --}}
@use('App\Services\Production\OwnProduction')
@php
    $title = $own->label();
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.production.products') => 'Hazır mallar', '#' => $title];
    $q = fn ($v) => OwnProduction::qty((float) $v);
    $matData = $materials->mapWithKeys(fn ($m) => [$m->id => ['name' => $m->name, 'unit' => $m->unitLabel(), 'cost' => (float) $m->avg_cost, 'stock' => (float) ($materialStocks[$m->id] ?? 0)]]);
    $recipeRows = old('recipe', $own->recipe->map(fn ($r) => ['raw_material_id' => $r->raw_material_id, 'quantity' => $q($r->quantity)])->all());
    $recipeCost = $own->recipe->sum(fn ($r) => (float) $r->quantity * (float) $r->material?->avg_cost);
    $low = $own->active && $stock['stock'] <= OwnProduction::LOW_STOCK;
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-7">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-5 d-flex align-items-start justify-content-end gap-2">
                <form method="POST" action="{{ route('admin.production.product.toggle', $own) }}" data-once>@csrf
                    <button class="btn btn-outline-secondary">{{ $own->active ? 'Deaktiv et' : 'Aktiv et' }}</button>
                </form>
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end" data-bs-toggle="modal" data-bs-target="#produceModal">
                    <span>Yeni partiya</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    @if($low)
        <div class="alert alert-danger">
            Stokda {{ $stock['stock'] }} ədəd qalıb{{ $waiting ? ' — '.$waiting['orders'].' sifariş ('.$waiting['quantity'].' ədəd) partiya gözləyir' : '' }}.
        </div>
    @endif

    <div class="card mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md"><div class="text-muted text-small">Stokda</div><div class="cta-3 {{ $low ? 'text-danger' : 'text-primary' }}">{{ $stock['stock'] }}</div></div>
            <div class="col-6 col-md"><div class="text-muted text-small">Orta maya</div><div class="cta-3">{{ number_format((float) $own->avg_cost, 2) }} ₼</div></div>
            <div class="col-6 col-md"><div class="text-muted text-small">İstehsal olunub</div><div class="cta-3">{{ $stock['produced'] }}</div></div>
            <div class="col-6 col-md"><div class="text-muted text-small">Sifarişlərə götürülüb</div><div class="cta-3">{{ $stock['held'] }}</div></div>
            <div class="col-6 col-md"><div class="text-muted text-small">Partiya gözləyir</div><div class="cta-3 {{ $waiting ? 'text-danger' : '' }}">{{ $waiting ? $waiting['quantity'] : 0 }}</div></div>
        </div>
        <div class="form-text mt-3">
            Saytdakı satış qiyməti: {{ number_format((float) $own->variant?->price, 2) }} ₼.
            Sifariş icraya götürüləndə Depo təminatında "Öz anbardan götür" ilə qalıqdan çıxılır; ləğv olunanda və ya qapıda imtina edilib anbara qaytarılanda geri düşür.
        </div>
    </div></div>

    <div class="row g-3 mb-5">
        <div class="col-lg-5">
            <h2 class="small-title">Resept — bir şüşəyə</h2>
            <div class="card h-100"><div class="card-body">
                <form method="POST" action="{{ route('admin.production.product.recipe', $own) }}" data-once>
                    @csrf
                    <div id="recipeRows">
                        @foreach($recipeRows as $i => $row)
                            <div class="row g-2 mb-2 align-items-center" data-recipe-row>
                                <div class="col-7"><select name="recipe[{{ $i }}][raw_material_id]" class="form-select" required>
                                    <option value="">Xammal</option>
                                    @foreach($materials as $m)<option value="{{ $m->id }}" @selected((int) ($row['raw_material_id'] ?? 0) === $m->id)>{{ $m->name }} ({{ $m->unitLabel() }})</option>@endforeach
                                </select></div>
                                <div class="col-4"><input type="number" step="0.001" min="0.001" name="recipe[{{ $i }}][quantity]" class="form-control" value="{{ $row['quantity'] ?? '' }}" placeholder="Miqdar" required></div>
                                <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-recipe-remove aria-label="Sil">✕</button></div>
                            </div>
                        @endforeach
                    </div>
                    <template id="recipeTemplate">
                        <div class="row g-2 mb-2 align-items-center" data-recipe-row>
                            <div class="col-7"><select name="recipe[__i__][raw_material_id]" class="form-select" required>
                                <option value="">Xammal</option>
                                @foreach($materials as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unitLabel() }})</option>@endforeach
                            </select></div>
                            <div class="col-4"><input type="number" step="0.001" min="0.001" name="recipe[__i__][quantity]" class="form-control" placeholder="Miqdar" required></div>
                            <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-recipe-remove aria-label="Sil">✕</button></div>
                        </div>
                    </template>
                    @if($materials->isEmpty())
                        <div class="text-muted mb-2">Əvvəlcə <a href="{{ route('admin.production.materials') }}">Xammal</a> bölməsində xammalı əlavə edin.</div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="recipeAdd" @disabled($materials->isEmpty())>+ Xammal</button>
                        <button class="btn btn-primary btn-sm">Yadda saxla</button>
                    </div>
                    @if($own->recipe->isNotEmpty())
                        <div class="form-text mt-3">Xammalın bu günkü orta qiyməti ilə bir şüşənin xammal xərci: {{ number_format($recipeCost, 2) }} ₼ (əmək və digər xərc partiyada əlavə olunur).</div>
                    @endif
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <h2 class="small-title">Partiyalar</h2>
            <div class="card h-100"><div class="card-body">
                @if($own->batches->isEmpty())
                    <div class="text-muted text-center py-4">Hələ partiya yoxdur. "Yeni partiya" ilə istehsalı yazın — əvvəlcədən hazır olan mal üçün xammalsız, yalnız mayası ilə yazmaq olar.</div>
                @else
                    <div class="table-responsive"><table class="table mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase text-end">Say</th>
                            <th class="text-muted text-small text-uppercase text-end">Xammal</th>
                            <th class="text-muted text-small text-uppercase text-end">Əlavə</th>
                            <th class="text-muted text-small text-uppercase text-end">1 şüşə</th>
                        </tr></thead>
                        <tbody>
                        @foreach($own->batches as $batch)
                            <tr>
                                <td>{{ $batch->produced_at->format('d.m.Y') }}
                                    <div class="text-alternate text-small">
                                        {{ $batch->materials->map(fn ($m) => ($m->material?->name ?? '—').' '.$q(abs((float) $m->quantity)).' '.$m->material?->unitLabel())->implode(', ') ?: 'Xammalsız' }}
                                        · {{ trim((string) $batch->creator?->full_name) ?: '—' }}@if($batch->note) · {{ $batch->note }}@endif
                                    </div></td>
                                <td class="text-end">{{ $batch->quantity }}</td>
                                <td class="text-end">{{ number_format((float) $batch->materials_cost, 2) }} ₼</td>
                                <td class="text-end">{{ number_format((float) $batch->extra_cost, 2) }} ₼</td>
                                <td class="text-end fw-bold">{{ number_format((float) $batch->unit_cost, 2) }} ₼</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div></div>
        </div>
    </div>
</div>

{{-- Yeni partiya: xammal reseptə görə (say × resept) doldurulur, faktiki sərf düzəldilə bilər --}}
<div class="modal modal-right fade" id="produceModal" tabindex="-1" aria-labelledby="produceTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.production.product.produce', $own) }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="produceTitle">Yeni partiya</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="produceQty">Neçə ədəd</label>
                <input type="number" min="1" step="1" id="produceQty" name="quantity" class="form-control" value="{{ old('quantity') }}" required></div>
            <div class="mb-3"><label class="form-label" for="produceDate">Tarix</label>
                <input type="date" id="produceDate" name="produced_at" class="form-control" max="{{ today()->toDateString() }}" value="{{ old('produced_at', today()->toDateString()) }}" required></div>
            <label class="form-label">Sərf olunan xammal (partiyaya)</label>
            <div id="produceLines"></div>
            <div class="mb-3"><button type="button" class="btn btn-outline-primary btn-sm" id="produceAdd" @disabled($materials->isEmpty())>+ Xammal</button></div>
            <div class="mb-3"><label class="form-label" for="produceExtra">Əlavə xərc, ₼</label>
                <input type="number" min="0" step="0.01" id="produceExtra" name="extra_cost" class="form-control" value="{{ old('extra_cost') }}" placeholder="Əmək, digər">
                <div class="form-text">Partiyanın ümumi əlavə xərci. Pulu ödəmisinizsə, Maliyyədə "Məxaric" kimi ayrıca yazın.</div></div>
            <div class="mb-3"><label class="form-label" for="produceNote">Qeyd</label>
                <input type="text" id="produceNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}"></div>
            <div class="alert alert-light mb-0" id="produceSummary"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">İstehsal et</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
<script>
    (() => {
        const materials = @json($matData);
        const recipe = @json($own->recipe->map(fn ($r) => ['id' => $r->raw_material_id, 'q' => (float) $r->quantity])->values());
        const oldLines = @json(old('lines'));
        const fmt = (v) => Number(v).toLocaleString('en-US', { maximumFractionDigits: 3, useGrouping: false });
        const money = (v) => Number(v).toFixed(2) + ' ₼';

        // Resept: sətir əlavə / sil
        const recipeBox = document.getElementById('recipeRows');
        const reindex = (box, prefix) => box.querySelectorAll('[data-recipe-row], [data-line]').forEach((row, i) =>
            row.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(new RegExp(prefix + '\\[(\\d+|__i__)]'), prefix + '[' + i + ']'); }));
        document.getElementById('recipeAdd').addEventListener('click', () => {
            recipeBox.appendChild(document.getElementById('recipeTemplate').content.cloneNode(true));
            reindex(recipeBox, 'recipe');
        });
        recipeBox.addEventListener('click', (e) => {
            if (!e.target.matches('[data-recipe-remove]')) return;
            e.target.closest('[data-recipe-row]').remove();
            reindex(recipeBox, 'recipe');
        });

        // Partiya: xammal sətirləri
        const box = document.getElementById('produceLines'), qty = document.getElementById('produceQty'), extra = document.getElementById('produceExtra');
        const options = Object.entries(materials).map(([id, m]) => '<option value="' + id + '">' + m.name + ' (' + m.unit + ')</option>').join('');
        const addLine = (id, amount, auto) => {
            const row = document.createElement('div');
            row.className = 'border rounded-md p-2 mb-2';
            row.dataset.line = '';
            if (auto) row.dataset.auto = '1';
            row.dataset.per = auto ? String(auto) : '';
            row.innerHTML = '<div class="row g-2 align-items-center"><div class="col-7"><select class="form-select" name="lines[0][raw_material_id]" required>' + options + '</select></div>'
                + '<div class="col-4"><input type="number" step="0.001" min="0" class="form-control" name="lines[0][quantity]"></div>'
                + '<div class="col-1 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove aria-label="Sil">✕</button></div></div>'
                + '<div class="text-small mt-1" data-info></div>';
            row.querySelector('select').value = id;
            row.querySelector('input').value = amount ?? '';
            box.appendChild(row);
        };
        const refresh = () => {
            reindex(box, 'lines');
            let cost = 0;
            box.querySelectorAll('[data-line]').forEach((row) => {
                const m = materials[row.querySelector('select').value], used = Number(row.querySelector('input').value) || 0;
                const info = row.querySelector('[data-info]');
                if (!m) { info.textContent = ''; return; }
                cost += used * m.cost;
                info.className = 'text-small mt-1 ' + (used > m.stock + 0.0005 ? 'text-danger' : 'text-muted');
                info.textContent = 'Stokda ' + fmt(m.stock) + ' ' + m.unit + ' · ' + money(used * m.cost);
            });
            const n = Number(qty.value) || 0, total = cost + (Number(extra.value) || 0);
            document.getElementById('produceSummary').textContent = n > 0
                ? 'Xammal ' + money(cost) + ' + əlavə ' + money(Number(extra.value) || 0) + ' = ' + money(total) + ' → bir şüşə ' + money(total / n)
                : 'Sayı yazın — xammal reseptə görə doldurulacaq.';
        };
        // Say dəyişəndə reseptdən gələn (əl ilə dəyişilməmiş) sətirlər yenidən hesablanır
        qty.addEventListener('input', () => {
            box.querySelectorAll('[data-auto]').forEach((row) => {
                const n = Number(qty.value) || 0;
                row.querySelector('input').value = n ? Math.round(Number(row.dataset.per) * n * 1000) / 1000 : '';
            });
            refresh();
        });
        box.addEventListener('input', (e) => { const row = e.target.closest('[data-line]'); if (row && e.target.matches('input')) delete row.dataset.auto; refresh(); });
        box.addEventListener('change', refresh);
        box.addEventListener('click', (e) => { if (e.target.matches('[data-remove]')) { e.target.closest('[data-line]').remove(); refresh(); } });
        extra.addEventListener('input', refresh);
        document.getElementById('produceAdd').addEventListener('click', () => { addLine(Object.keys(materials)[0], ''); refresh(); });

        if (oldLines) {
            Object.values(oldLines).forEach((l) => addLine(String(l.raw_material_id), l.quantity));
        } else {
            recipe.forEach((r) => addLine(String(r.id), '', r.q));
        }
        qty.dispatchEvent(new Event('input'));
        @if($errors->hasAny(['quantity', 'produced_at', 'lines', 'extra_cost', 'note']) || $errors->has('lines.*'))
            new bootstrap.Modal(document.getElementById('produceModal')).show();
        @endif
    })();
</script>
@endsection
