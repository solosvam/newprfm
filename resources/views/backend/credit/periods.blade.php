@php
    $html_tag_data = [];
    $title = 'Kredit faizləri';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>"Kredit faizləri"];
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4">{{$title}}</h1>
        @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.credit.periods.update') }}">
                @csrf
                <div id="creditPeriods">
                    @forelse(old('periods', $periods->map(fn($p) => [
                        'id'=>$p->id, 'month'=>$p->month, 'interest_rate'=>$p->interest_rate, 'active'=>$p->active
                    ])->toArray()) as $index => $period)
                        <div class="row g-2 mb-2 credit-period-row">
                            <input type="hidden" class="period-id" value="{{ $period['id'] ?? '' }}">
                            <div class="col-md-4">
                                <label class="form-label">Ay</label>
                                <input type="number" min="1" class="form-control period-month" value="{{ $period['month'] ?? '' }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Faiz (%)</label>
                                <input type="number" min="0" step="0.01" class="form-control period-rate" value="{{ $period['interest_rate'] ?? '' }}" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end pb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input period-active" type="checkbox" value="1" @checked((bool)($period['active'] ?? false))>
                                    <label class="form-check-label">Aktiv</label>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-outline-danger remove-period mb-1" type="button">-</button>
                            </div>
                        </div>
                    @empty
                        <div class="row g-2 mb-2 credit-period-row">
                            <input type="hidden" class="period-id">
                            <div class="col-md-4"><label class="form-label">Ay</label><input type="number" min="1" class="form-control period-month" required></div>
                            <div class="col-md-4"><label class="form-label">Faiz (%)</label><input type="number" min="0" step="0.01" class="form-control period-rate" required></div>
                            <div class="col-md-2 d-flex align-items-end pb-2"><div class="form-check form-switch"><input class="form-check-input period-active" type="checkbox" value="1" checked><label class="form-check-label">Aktiv</label></div></div>
                            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-danger remove-period mb-1" type="button">-</button></div>
                        </div>
                    @endforelse
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button id="addCreditPeriod" class="btn btn-outline-primary" type="button">+ Yeni müddət</button>
                    <button class="btn btn-primary" type="submit">Yadda saxla</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js_page')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const area = document.getElementById('creditPeriods');

    function reindex() {
        area.querySelectorAll('.credit-period-row').forEach((row, index) => {
            row.querySelector('.period-id').name = `periods[${index}][id]`;
            row.querySelector('.period-month').name = `periods[${index}][month]`;
            row.querySelector('.period-rate').name = `periods[${index}][interest_rate]`;
            row.querySelector('.period-active').name = `periods[${index}][active]`;
        });
    }

    document.getElementById('addCreditPeriod').addEventListener('click', function () {
        const row = area.querySelector('.credit-period-row').cloneNode(true);
        row.querySelector('.period-id').value = '';
        row.querySelector('.period-month').value = '';
        row.querySelector('.period-rate').value = '';
        row.querySelector('.period-active').checked = true;
        area.appendChild(row);
        reindex();
    });

    area.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-period')) return;
        if (area.querySelectorAll('.credit-period-row').length === 1) {
            const row = event.target.closest('.credit-period-row');
            row.querySelector('.period-id').value = '';
            row.querySelector('.period-month').value = '';
            row.querySelector('.period-rate').value = '';
            row.querySelector('.period-active').checked = true;
        } else {
            event.target.closest('.credit-period-row').remove();
        }
        reindex();
    });

    reindex();
});
</script>
@endsection
