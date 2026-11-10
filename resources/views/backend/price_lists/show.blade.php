@php
    $html_tag_data = [];
    $title = $list->warehouse->name_az.' — price list';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.price-lists.index') => 'Price listlər', '#' => $list->warehouse->name_az];
    $matchLabels = ['auto' => 'avtomatik', 'memory' => 'yaddaşdan', 'manual' => 'əl ilə'];
    $kinds = ['EDP' => 'EDP', 'EDT' => 'EDT', 'EDC' => 'EDC', 'PARFUM' => 'Parfum', 'EXTRAIT' => 'Extrait'];
    $genders = ['L' => 'qadın', 'M' => 'kişi', 'U' => 'unisex'];
@endphp

@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-8">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-4 text-sm-end text-muted text-small">
                    {{ $list->file_name }}<br>{{ $list->created_at->format('d.m.Y H:i') }}
                    @unless($isCurrent)<br><span class="badge bg-outline-warning">köhnə siyahı — deponun daha yenisi var</span>@endunless
                </div>
            </div>
        </div>

        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        {{-- Bizim brendə bağlanmayan brendlər: sətirləri avtomatik uyğunlaşa bilmir. Bir dəfə bağlanır, bütün depolar üçün yadda qalır. --}}
        @if($unknownBrands->isNotEmpty())
            <div class="card d-flex mb-3">
                <div class="d-flex flex-grow-1" role="button" data-bs-toggle="collapse" data-bs-target="#plBrands" aria-expanded="false" aria-controls="plBrands">
                    <div class="card-body py-3">
                        <div class="list-item-heading">Tanınmayan brendlər <span class="badge bg-warning ms-2">{{ $unknownBrands->count() }}</span></div>
                        <div class="text-muted text-small">{{ $unknownBrands->sum() }} sətir bu brendlərə görə avtomatik uyğunlaşmayıb. Brendi bizimki ilə bağlayın — sətirlər yenidən yoxlanacaq.</div>
                    </div>
                </div>
                <div id="plBrands" class="collapse">
                    <div class="card-body accordion-content pt-0">
                        <div class="table-responsive"><table class="table align-middle mb-0">
                            <thead><tr>
                                <th class="text-muted text-small text-uppercase">Fayldakı brend</th>
                                <th class="text-muted text-small text-uppercase">Bizdə var — bağla</th>
                                <th class="text-muted text-small text-uppercase">Bizdə yoxdur — yarat</th>
                            </tr></thead>
                            <tbody>
                            @foreach($unknownBrands as $raw => $count)
                                <tr>
                                    <td>{{ $raw }}<div class="text-muted text-small">{{ $count }} sətir</div></td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.price-lists.brands.match', $list) }}" class="d-flex gap-2">
                                            @csrf <input type="hidden" name="brand_raw" value="{{ $raw }}">
                                            <select name="brand_id" class="form-select form-select-sm" required aria-label="{{ $raw }} — bizim brend">
                                                <option value="">Bizim brendi seçin</option>
                                                @foreach($brands as $id => $name)<option value="{{ $id }}">{{ html_entity_decode($name) }}</option>@endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Bağla</button>
                                        </form>
                                    </td>
                                    <td>
                                        @can('brands.menu')
                                            {{-- Ad rəsmi yazılışla düzəldilə bilər (fayldakı böyük hərflərdən təxmin edilib) --}}
                                            <form method="POST" action="{{ route('admin.price-lists.brands.create', $list) }}" class="d-flex gap-2">
                                                @csrf <input type="hidden" name="brand_raw" value="{{ $raw }}">
                                                <input type="text" name="name" class="form-control form-control-sm" maxlength="255" required value="{{ \Illuminate\Support\Str::title(mb_strtolower($raw)) }}" aria-label="{{ $raw }} — yeni brendin adı">
                                                <button class="btn btn-sm btn-primary text-nowrap">Brend yarat</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        <div class="form-text mt-2">"Bağla" — brend bizdə başqa adla var (məs. YSL = Yves Saint Laurent). "Brend yarat" — bizdə yoxdur: yaradılır və bağlanır, loqosu və məhsulları sonra Kataloq bölməsindən əlavə olunur.</div>
                    </div>
                </div>
            </div>
        @endif

        <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title mb-3">
            @foreach(['unmatched' => ['Uyğunlaşmayan', $total - $matched], 'matched' => ['Uyğunlaşan', $matched], 'all' => ['Hamısı', $total]] as $key => [$label, $count])
                <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.price-lists.show', [$list, 'tab' => $key]) }}">{{ $label }} <span class="text-muted">· {{ $count }}</span></a></li>
            @endforeach
            {{-- Eyni deponun əvvəlki siyahısı ilə müqayisə (yeni gələn, çıxan, qiyməti dəyişən) --}}
            @if($diff)
                <li class="nav-item"><a class="nav-link {{ $tab === 'diff' ? 'active' : '' }}" href="{{ route('admin.price-lists.show', [$list, 'tab' => 'diff']) }}">Əvvəlki ilə fərq
                    <span class="text-muted">· {{ $diff['added']->count() + $diff['removed']->count() + $diff['changed']->count() }}</span></a></li>
            @endif
        </ul>

        @if($tab === 'diff' && $diff)
            @php $diffLabels = ['added' => ['Yeni gələnlər', $diff['added']->count()], 'removed' => ['Çıxanlar', $diff['removed']->count()], 'changed' => ['Qiyməti dəyişənlər', $diff['changed']->count()]]; @endphp
            <p class="text-muted mb-2">Müqayisə: <a href="{{ route('admin.price-lists.show', $diff['previous']) }}">{{ $diff['previous']->file_name }}</a> ({{ $diff['previous']->created_at->format('d.m.Y H:i') }}) → bu siyahı.</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($diffLabels as $key => [$label, $count])
                    <a href="{{ route('admin.price-lists.show', [$list, 'tab' => 'diff', 'type' => $key]) }}" class="btn btn-sm {{ $diffType === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }} · {{ $count }}</a>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('admin.price-lists.show', $list) }}" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($tab === 'diff')<input type="hidden" name="type" value="{{ $diffType }}">@endif
            <div class="col-12 col-sm-5 col-lg-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Adda axtar"></div>
            <div class="col-8 col-sm-4 col-lg-3">
                <select name="brand" class="form-select">
                    <option value="">Bütün brendlər</option>
                    @foreach($brandNames as $name)<option value="{{ $name }}" @selected(request('brand') === $name)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>

        @if($tab === 'diff' && $diffRows)
            @php
                $variantName = fn ($v) => $v ? trim(html_entity_decode($v->product?->brand?->name ?? '').' '.($v->product?->name ?? 'Silinmiş məhsul')).' · '.$v->size?->name_az : null;
            @endphp
            <div class="card mb-3"><div class="card-body">
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr>
                        <th class="text-muted text-small text-uppercase">Depodakı ad</th>
                        @if($diffType === 'changed')
                            <th class="text-muted text-small text-uppercase text-end">Köhnə</th>
                            <th class="text-muted text-small text-uppercase text-end">Yeni</th>
                            <th class="text-muted text-small text-uppercase text-end">Fərq</th>
                        @else
                            <th class="text-muted text-small text-uppercase text-end">Alış</th>
                        @endif
                        <th class="text-muted text-small text-uppercase">Bizim məhsul</th>
                    </tr></thead>
                    <tbody>
                    @forelse($diffRows['rows'] as $row)
                        @php $item = is_array($row) ? $row['new'] : $row; @endphp
                        <tr>
                            <td>{{ $item->raw_name }}
                                @if(is_array($row) && $row['old']->raw_name !== $item->raw_name)<div class="text-muted text-small">əvvəl: {{ $row['old']->raw_name }}</div>@endif
                            </td>
                            @if($diffType === 'changed')
                                @php $delta = (float) $row['new']->price - (float) $row['old']->price; @endphp
                                <td class="text-end text-nowrap text-muted">{{ number_format((float) $row['old']->price, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $row['new']->price, 2) }} AZN</td>
                                <td class="text-end text-nowrap {{ $delta > 0 ? 'text-danger' : 'text-success' }}">{{ $delta > 0 ? '↑ +' : '↓ ' }}{{ number_format($delta, 2) }}
                                    <span class="text-small">({{ $delta > 0 ? '+' : '' }}{{ \App\Services\PriceList\PriceListDiff::percent($row['old'], $row['new']) }}%)</span></td>
                            @else
                                <td class="text-end text-nowrap">{{ number_format((float) $item->price, 2) }} AZN</td>
                            @endif
                            <td>
                                @if($item->variant)
                                    {{ $variantName($item->variant) }}
                                    <div class="text-muted text-small">satış {{ number_format((float) $item->variant->price, 2) }} AZN</div>
                                    @if($diffType === 'removed')
                                        @if($where = $diffRows['elsewhere'][$item->product_variant_id] ?? null)
                                            <div class="text-small">başqa depoda: {{ $where }}</div>
                                        @else
                                            <span class="badge bg-outline-danger">başqa depoların siyahısında yoxdur</span>
                                        @endif
                                    @endif
                                @else
                                    <span class="text-muted text-small">bağlanmayıb</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Fərq yoxdur.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div></div>

            @include('backend._layout.simple-pagination', ['paginator' => $diffRows['rows']])
        @else
        <div class="card mb-3"><div class="card-body">
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Depodakı ad</th>
                    <th class="text-muted text-small text-uppercase text-end">Alış</th>
                    <th class="text-muted text-small text-uppercase">Bizim məhsul</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr data-price-item="{{ $item->id }}">
                        <td>{{ $item->raw_name }}
                            <div class="text-muted text-small">
                                {{ collect([$kinds[$item->kind] ?? null, $genders[$item->gender] ?? null, $item->volume_ml !== null ? (float) $item->volume_ml.' ml' : null, $item->tester ? 'tester' : null, $item->is_set ? 'dəst' : null])->filter()->implode(' · ') }}
                                @if(!$item->brand_id && $item->brand_raw) <span class="text-warning">· brend tanınmır</span>@endif
                            </div>
                        </td>
                        <td class="text-end text-nowrap">{{ number_format((float) $item->price, 2) }} AZN</td>
                        <td>
                            @if($item->variant)
                                {{ html_entity_decode($item->variant->product?->brand?->name ?? '') }} {{ $item->variant->product?->name ?? 'Silinmiş məhsul' }} · {{ $item->variant->size?->name_az }}
                                <div class="text-muted text-small">{{ $matchLabels[$item->matched_by] ?? '' }} · satış {{ number_format((float) $item->variant->price, 2) }} AZN</div>
                            @elseif($item->product_variant_id)
                                <span class="text-danger">Bağlandığı variant silinib</span>
                            @else
                                {{-- Namizədlərin sayı: "Uyğunlaşdır" basmadan oxşarı olub-olmadığı görünsün --}}
                                @php [$all, $exact, $sameSize] = $similar[$item->id] ?? [0, 0, 0]; @endphp
                                @if($exact)<span class="badge bg-success">{{ $exact }} eyni ad və ölçü</span>
                                @elseif($sameSize)<span class="badge bg-outline-primary">{{ $sameSize }} oxşar · eyni ölçü</span>@if($all > $sameSize) <span class="text-muted text-small">+{{ $all - $sameSize }} başqa ölçü</span>@endif
                                @elseif($all)<span class="badge bg-outline-muted">{{ $all }} oxşar · başqa ölçü</span>
                                @else<span class="text-muted text-small">oxşarı yoxdur</span>@endif
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm {{ $item->product_variant_id ? 'btn-link px-1' : 'btn-outline-primary' }}" data-bs-toggle="modal" data-bs-target="#plMatchModal"
                                    data-candidates="{{ route('admin.price-lists.items.candidates', $item) }}" data-match="{{ route('admin.price-lists.items.match', $item) }}"
                                    data-title="{{ $item->raw_name }}" data-price="{{ number_format((float) $item->price, 2) }}">{{ $item->product_variant_id ? 'Dəyiş' : 'Uyğunlaşdır' }}</button>
                            @if($item->product_variant_id)
                                <button type="button" class="btn btn-sm btn-link px-1 text-danger" data-unmatch="{{ route('admin.price-lists.items.unmatch', $item) }}">Ayır</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">{{ $tab === 'unmatched' ? 'Uyğunlaşmayan sətir yoxdur.' : 'Sətir tapılmadı.' }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>

        @include('backend._layout.simple-pagination', ['paginator' => $items])
        @endif
    </div>

    {{-- Uyğunlaşdırma: namizədlər (eyni brend və ad) və bizim məhsullarda axtarış --}}
    <div class="modal modal-right large fade" id="plMatchModal" tabindex="-1" aria-labelledby="plMatchTitle" aria-hidden="true" data-csrf="{{ csrf_token() }}">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="plMatchTitle">Uyğunlaşdır</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="mb-3"><div class="fw-bold" data-match-title></div><div class="text-muted text-small">Alış: <span data-match-price></span> AZN</div></div>
                <input type="search" class="form-control mb-3" placeholder="Bizim məhsullarda axtar (ad, brend, ölçü)" data-match-search aria-label="Bizim məhsullarda axtar">
                <div data-match-results><p class="text-muted">Yüklənir…</p></div>
                <div class="form-text mt-3">Seçim yadda qalır: bu deponun növbəti price listində eyni ad avtomatik bağlanacaq.</div>
            </div>
        </div></div>
    </div>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/price-lists.js') }}"></script>
@endsection
