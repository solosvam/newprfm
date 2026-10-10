@php
    $html_tag_data = [];
    $title = 'Sütunların seçimi';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.price-lists.index') => 'Price listlər', '#' => $title];
    $letter = fn (int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $cell = fn ($value) => is_float($value) ? rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') : (string) $value;
@endphp

@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
            @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
        </div>

        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="alert alert-info">
            <strong>{{ $warehouse->name_az }}</strong> · {{ $upload['name'] }}.
            @if($remembered) Bu anbar üçün əvvəlki seçim hazır gəlib — düzdürsə, sadəcə "İmport et" basın.
            @else Sütunlar təxmin edilib — aşağıdakı önizləməyə baxıb düzəldin. @endif
        </div>

        <form method="POST" action="{{ route('admin.price-lists.import', $token) }}">
            @csrf
            <div class="card mb-3"><div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label" for="mapSheet">Vərəq</label>
                        <select id="mapSheet" name="sheet" class="form-select" data-map-sheet="{{ route('admin.price-lists.map', $token) }}">
                            @foreach($sheets as $i => $name)<option value="{{ $i }}" @selected((int) $mapping['sheet'] === $i)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="mapFirst">Məlumat neçənci sətirdən başlayır</label>
                        <input id="mapFirst" type="number" name="first_row" min="1" class="form-control" value="{{ old('first_row', $mapping['first_row']) }}" required>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="mapName">Ad sütunu</label>
                        <select id="mapName" name="name_col" class="form-select">
                            @for($i = 0; $i < $columns; $i++)<option value="{{ $i }}" @selected((int) old('name_col', $mapping['name_col']) === $i)>{{ $letter($i) }}</option>@endfor
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="mapPrice">Qiymət sütunu (AZN)</label>
                        <select id="mapPrice" name="price_col" class="form-select">
                            @for($i = 0; $i < $columns; $i++)<option value="{{ $i }}" @selected((int) old('price_col', $mapping['price_col']) === $i)>{{ $letter($i) }}</option>@endfor
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label" for="mapBrandMode">Brend haradadır</label>
                        <select id="mapBrandMode" name="brand_mode" class="form-select">
                            @foreach(['group' => 'Qrup başlığı (qiymətsiz sətir)', 'column' => 'Ayrıca sütun', 'none' => 'Brend yoxdur'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('brand_mode', $mapping['brand_mode']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2" data-map-brand-col>
                        <label class="form-label" for="mapBrand">Brend sütunu</label>
                        <select id="mapBrand" name="brand_col" class="form-select">
                            <option value="">—</option>
                            @for($i = 0; $i < $columns; $i++)<option value="{{ $i }}" @selected(old('brand_col', $mapping['brand_col']) !== null && (int) old('brand_col', $mapping['brand_col']) === $i)>{{ $letter($i) }}</option>@endfor
                        </select>
                    </div>
                </div>
                <div class="form-text mt-3">Həcm, növ (EDP / EDT), cins (L / M / UNISEX) və "TESTER" addan avtomatik oxunur. Qiymət 2 onluğa yuvarlaqlaşdırılır.</div>
            </div></div>

            <h2 class="small-title">Faylın ilk sətirləri</h2>
            <div class="card mb-3"><div class="card-body">
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th class="text-muted text-small">#</th>@for($i = 0; $i < $columns; $i++)<th class="text-muted text-small text-uppercase">{{ $letter($i) }}</th>@endfor</tr></thead>
                    <tbody>
                    @foreach($rows as $n => $row)
                        <tr><td class="text-muted">{{ $n + 1 }}</td>@for($i = 0; $i < $columns; $i++)<td style="white-space: pre-wrap">{{ $cell($row[$i] ?? '') }}</td>@endfor</tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div></div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary">İmport et</button>
                <a class="btn btn-outline-primary" href="{{ route('admin.price-lists.index') }}">Ləğv et</a>
            </div>
        </form>
    </div>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/price-lists.js') }}"></script>
@endsection
