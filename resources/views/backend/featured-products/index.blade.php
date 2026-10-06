@php
    $html_tag_data = [];
    $title = 'Vitrin (Populyar)';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Sayt', '#' => $title];
    $image = fn ($product) => ($img = $product?->images->first()) ? asset('frontend/uploads/products/'.$img->image) : null;
    $full = $featured->count() >= $limit;
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <style>
        .featured-list { list-style: none; margin: 0; padding: 0; }
        .featured-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-bottom: 1px solid var(--separator-light); background: var(--foreground); }
        .featured-item:last-child { border-bottom: 0; }
        .featured-item.sortable-ghost { opacity: .4; }
        .featured-item__handle { cursor: grab; color: var(--muted); font-size: 18px; line-height: 1; padding: 4px; user-select: none; }
        .featured-item__pos { width: 26px; text-align: center; font-weight: 700; color: var(--primary); }
        .featured-thumb { width: 48px; height: 48px; flex: 0 0 48px; border-radius: 8px; border: 1px solid var(--separator-light); background: var(--background) center / contain no-repeat; }
        .featured-item__info { flex: 1; min-width: 0; }
        .featured-item__info .text-truncate { max-width: 100%; }
    </style>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/sortable.min.js') }}"></script>
    <script>
        // Canlı axtarış: yazdıqca (250 ms gözləyib), "Axtar" düyməsi yoxdur
        (() => {
            const input = document.getElementById('featuredSearch');
            if (!input) return;
            const box = document.getElementById('featuredSearchResults');
            const status = document.getElementById('featuredSearchStatus');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            let timer = null;
            let request = null;

            const say = (text) => { status.textContent = text || ''; status.hidden = !text; };

            function row(product) {
                const item = document.createElement('div');
                item.className = 'd-flex align-items-center gap-2 py-2 border-top';
                const thumb = document.createElement('span');
                thumb.className = 'featured-thumb';
                if (product.image) thumb.style.backgroundImage = `url('${product.image}')`;
                const info = document.createElement('div');
                info.className = 'flex-grow-1 min-w-0';
                const name = document.createElement('div');
                name.className = 'text-truncate';
                name.textContent = product.name;
                info.append(name);
                if (product.price) {
                    const price = document.createElement('div');
                    price.className = 'text-small text-muted';
                    price.textContent = `${product.price} ₼-dən`;
                    info.append(price);
                }
                const add = document.createElement('button');
                add.type = 'button';
                add.className = 'btn btn-sm btn-outline-primary';
                add.textContent = 'Əlavə et';
                add.addEventListener('click', async () => {
                    add.disabled = true;
                    try {
                        const response = await fetch(input.dataset.storeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ product_id: product.id }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(data.message || 'Əlavə olunmadı');
                        // vitrin siyahısı yenilənsin; axtarış mətni qalsın
                        location.href = `${location.pathname}?q=${encodeURIComponent(input.value.trim())}`;
                    } catch (error) {
                        add.disabled = false;
                        say(error.message);
                    }
                });
                item.append(thumb, info, add);
                return item;
            }

            function run() {
                const q = input.value.trim();
                if (request) request.abort();
                if (q.length < 2) { box.replaceChildren(); say(''); return; }
                request = new AbortController();
                say('Axtarılır…');
                fetch(`${input.dataset.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: request.signal })
                    .then((response) => { if (!response.ok) throw new Error(`Xəta (${response.status})`); return response.json(); })
                    .then((data) => {
                        box.replaceChildren(...data.results.map(row));
                        say(data.results.length ? '' : `"${q}" üzrə vitrində olmayan aktiv ətir tapılmadı`);
                    })
                    .catch((error) => { if (error.name !== 'AbortError') say(error.message); });
            }

            input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 250); });
            const initial = new URLSearchParams(location.search).get('q');
            if (initial && !input.disabled) { input.value = initial; run(); }
            if (!input.disabled) input.focus();
        })();

        // Sürüşdürərək sırala → yeni ardıcıllıq dərhal yadda saxlanır
        (() => {
            const list = document.getElementById('featuredList');
            if (!list || typeof Sortable === 'undefined') return;
            const status = document.getElementById('featuredOrderStatus');
            const renumber = () => list.querySelectorAll('[data-pos]').forEach((el, i) => { el.textContent = i + 1; });

            Sortable.create(list, {
                handle: '.featured-item__handle',
                animation: 150,
                onEnd: async () => {
                    renumber();
                    status.textContent = 'Saxlanılır…';
                    status.className = 'text-small text-muted';
                    try {
                        const response = await fetch(list.dataset.reorderUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json', Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ ids: [...list.children].map((li) => Number(li.dataset.id)) }),
                        });
                        if (!response.ok) throw new Error();
                        status.textContent = 'Ardıcıllıq yadda saxlanıldı';
                        status.className = 'text-small text-success';
                    } catch (e) {
                        status.textContent = 'Saxlanılmadı — səhifəni yeniləyin';
                        status.className = 'text-small text-danger';
                    }
                },
            });
        })();
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-8">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-4 d-flex align-items-start justify-content-sm-end">
                    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-primary btn-sm">Ana səhifəyə bax</a>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            {{-- Vitrin --}}
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h2 class="small-title mb-0">Vitrindəki ətirlər</h2>
                            <span class="badge {{ $full ? 'bg-primary' : 'bg-outline-primary' }}">{{ $featured->count() }} / {{ $limit }}</span>
                        </div>
                        <p class="text-small text-muted mb-3">
                            Ana səhifədə "Populyar" sıralamasında birinci səhifədə bu ardıcıllıqla görünür.
                            {{ $limit }}-dən az seçilsə, boş yerləri ən yeni ətirlər tutur. Sırasını dəyişmək üçün ⋮⋮ işarəsindən tutub sürüşdürün.
                        </p>
                        <div id="featuredOrderStatus" class="text-small text-muted mb-2"></div>

                        @if($featured->isEmpty())
                            <div class="text-center text-muted py-5">Vitrin boşdur — sağdakı axtarışla ətir əlavə edin.<br>Boş olanda ana səhifə ən yeni ətirləri göstərir.</div>
                        @else
                            <ul class="featured-list border rounded" id="featuredList" data-reorder-url="{{ route('admin.featured.reorder') }}">
                                @foreach($featured as $row)
                                    @php $product = $row->product; @endphp
                                    <li class="featured-item" data-id="{{ $row->id }}">
                                        <span class="featured-item__handle" title="Sürüşdür" aria-hidden="true">⋮⋮</span>
                                        <span class="featured-item__pos" data-pos>{{ $loop->iteration }}</span>
                                        <span class="featured-thumb" @if($image($product)) style="background-image: url('{{ $image($product) }}')" @endif></span>
                                        <div class="featured-item__info">
                                            <div class="fw-bold text-truncate">{{ $product?->brand?->name }} {{ $product?->name }}</div>
                                            <div class="text-small text-muted">
                                                @if($product?->variants->isNotEmpty())
                                                    {{ number_format((float) $product->variants->first()->price, 2) }} ₼-dən
                                                @endif
                                                @if(!$product?->active)
                                                    <span class="badge bg-outline-danger ms-1" title="Deaktiv məhsul saytda görünmür">deaktiv</span>
                                                @elseif($product->variants->isEmpty())
                                                    <span class="badge bg-outline-warning ms-1" title="Aktiv ölçüsü yoxdur">ölçü yoxdur</span>
                                                @endif
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('admin.featured.destroy', $row) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Vitrindən çıxar">Çıxar</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Ətir əlavə et --}}
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="small-title mb-1">Ətir əlavə et</h2>
                        <p class="text-small text-muted mb-3">Boşluqdan əvvəl brend, sonra ətirin adı: <strong>dol int</strong> → Dolce &amp; Gabbana … Intense. Boşluqsuz yazılan həm brenddə, həm adda axtarılır.</p>
                        <input type="search" id="featuredSearch" class="form-control mb-3" placeholder="Məs. dol int, chr sau" aria-label="Ətir axtar"
                               autocomplete="off" data-search-url="{{ route('admin.featured.search') }}" data-store-url="{{ route('admin.featured.store') }}"
                               @disabled($full)>

                        @if($full)
                            <div class="alert alert-info text-small mb-3">Vitrin doludur ({{ $limit }}/{{ $limit }}). Yeni ətir əlavə etmək üçün əvvəlcə birini çıxarın.</div>
                        @endif

                        <div id="featuredSearchStatus" class="text-muted text-center text-small py-2" hidden></div>
                        <div id="featuredSearchResults"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
