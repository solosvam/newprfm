{{--
  Maliyyə kateqoriyaları: Mədaxil / Məxaric / Hər ikisi, iki səviyyəli ağac.
  Sistem kateqoriyaları (kilid) dəyişdirilmir və silinmir; istifadə olunan kateqoriya silinmir.
--}}
@php
    $html_tag_data = [];
    $title = 'Kateqoriyalar';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Maliyyə', '' => 'Kateqoriyalar'];
    $tone = ['income' => 'bg-outline-success', 'expense' => 'bg-outline-danger', 'both' => 'bg-outline-primary'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/finance.js') }}"></script>
@endsection

@section('content')
<div class="container finance-page">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#categoryModal"
                        data-action="{{ route('admin.finance.categories.store') }}" data-title="Yeni kateqoriya">
                    <span>Yarat</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>@foreach(['Ad', 'Növ', 'Əməliyyat', ''] as $th)<th class="text-muted text-small text-uppercase">{{ $th }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($categories as $c)
                <tr>
                    <td class="{{ $c->parent_id ? 'ps-5' : 'fw-bold' }}">{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</td>
                    <td><span class="badge {{ $tone[$c->type] ?? 'bg-outline-muted' }}">{{ $c->typeLabel() }}</span></td>
                    <td class="text-alternate">{{ $c->movements_count ?: '—' }}</td>
                    <td class="text-end text-nowrap">
                        @if($c->system)
                            <span class="text-muted" title="Sistem kateqoriyası — dəyişdirilmir"><i data-acorn-icon="lock-on" data-acorn-size="16"></i></span>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal"
                                    data-action="{{ route('admin.finance.categories.update', $c) }}" data-method="PUT" data-title="Kateqoriyanı redaktə et"
                                    data-name="{{ $c->name }}" data-type="{{ $c->type }}" data-parent="{{ $c->parent_id }}" data-id="{{ $c->id }}"
                                    data-has-children="{{ $c->children_count ? 1 : 0 }}">Redaktə</button>
                            @if(!$c->movements_count && !$c->children_count)
                                <form method="POST" action="{{ route('admin.finance.categories.destroy', $c) }}" class="d-inline" data-confirm="&quot;{{ $c->name }}&quot; silinsin?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Sil</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.categories.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="categoryModalTitle">Yeni kateqoriya</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="catName">Ad</label>
                <input id="catName" name="name" maxlength="120" class="form-control" value="{{ old('name') }}" required></div>
            <div class="mb-3"><label class="form-label" for="catType">Növ</label>
                <select id="catType" name="type" class="form-select" required>
                    @foreach(\App\Models\Finance\FinanceCategory::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', 'expense') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <label class="form-label" for="catParent">Üst kateqoriya <small class="text-muted">(boş — əsas)</small></label>
            <select id="catParent" name="parent_id" class="form-select">
                <option value="">—</option>
                {{-- Avtomatik kateqoriyaların (Satış, Köçürmə...) altına kateqoriya yaradılmır; növ uyğun olmalıdır (finance.js) --}}
                @foreach($parents as $p)
                    @continue(in_array($p->code, \App\Models\Finance\FinanceCategory::AUTO, true))
                    <option value="{{ $p->id }}" data-type="{{ $p->type }}" @selected((int) old('parent_id') === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Məs.: "Əməkhaqqı" → "Bonus". Hesabatda alt kateqoriyalar üst kateqoriyanın altında görünür.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Yadda saxla</button></div>
    </form></div>
</div>
@endsection
