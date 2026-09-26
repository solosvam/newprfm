@extends('frontend.new.layouts.app')

@section('title', 'parfumshop — Ana səhifə')

@section('subnav')
    <div class="wrap">
        <nav class="cats">
            <a href="#" class="active">Hamısı</a>
            <a href="#">Qadın</a>
            <a href="#">Kişi</a>
            <a href="#">Unisex</a>
            <a href="#">Yeni</a>
            <a href="#">Ekskluziv</a>
            <a href="#">Testerlər</a>
            <a href="#">Hədiyyə kartları</a>
        </nav>
    </div>
@endsection

@section('content')

    <div class="hero">
        <p class="eyebrow">YENİ KOLLEKSİYA</p>
        <h1>Payızın ətri</h1>
        <p>Odunsu və ədviyyəli notlarla 90+ brenddən yeni sezon seçkisi</p>
        <a class="btn-primary" href="#">Kolleksiyaya bax</a>
    </div>

    <div class="brands">
        @foreach (['Chanel','Dior','Creed','Amouage','Givenchy','Burberry'] as $brand)
            <div class="brand-card">{{ $brand }}</div>
        @endforeach
        <div class="brand-card more">+94 brend</div>
    </div>

    <div class="section-head">
        <h2>Ən çox satılanlar</h2>
        <a href="#">Hamısına bax</a>
    </div>

    <div class="layout">
        <div class="filters">
            <div class="filter-group">
                <p class="label">Qiymət</p>
                <input type="range" min="40" max="2400">
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-top:4px;">
                    <span>40 ₼</span><span>2400 ₼</span>
                </div>
            </div>
            <div class="filter-group">
                <p class="label">Ətrin növü</p>
                <label class="filter-row"><input type="checkbox"> Eau de Parfum</label>
                <label class="filter-row"><input type="checkbox"> Eau de Toilette</label>
                <label class="filter-row"><input type="checkbox"> Eau de Cologne</label>
            </div>
            <div class="filter-group">
                <p class="label">Qoxu qrupu</p>
                <label class="filter-row"><input type="checkbox"> Fujer</label>
                <label class="filter-row"><input type="checkbox"> Şipr</label>
                <label class="filter-row"><input type="checkbox"> Şərq</label>
                <label class="filter-row"><input type="checkbox"> Ağac</label>
            </div>
        </div>

        <div>
            <div class="toolbar">
                <span>{{ $products->total() ?? 1133 }} nəticə</span>
                <span>Sırala: Ən yenilər</span>
            </div>
            <div class="grid">
                @foreach ($products ?? [] as $product)
                    @php
                        $image = $product->images->first();
                        $gender = $product->genders->first();
                        $variants = $product->variants->where('active', 1);
                        $firstVariant = $variants->first();
                        $locale = app()->getLocale();

                        $genderName = $gender
                            ? ($gender->{'name_' . $locale} ?? $gender->name_az)
                            : null;

                        $typeName = $product->type
                            ? ($product->type->{'name_' . $locale} ?? $product->type->name_az)
                            : null;
                    @endphp
                    <div class="card" data-href="{{ route('newproduct', $product->slug) }}">
                        <div class="thumb">
                            <span class="badge">3</span>

                            <div class="thumb-actions">
                                <button
                                    type="button"
                                    class="icon-btn fav-btn {{ ($product->id == '1137') ? 'active' : '' }}"
                                    data-product-id="{{ $product->id }}"
                                    aria-label="Seçilmişlərə əlavə et"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                                </button>

                                <button
                                    type="button"
                                    class="icon-btn share-btn"
                                    data-url="{{ route('newproduct', $product->slug) }}"
                                    data-title="{{ $product->name }}"
                                    aria-label="Paylaş"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                                </button>
                            </div>

                            @if ($image)
                                <img
                                    src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                    alt="{{ $product->name }}"
                                    loading="lazy"
                                >
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                            @endif
                        </div>

                        <p class="brandname">{{ $product->brand?->name }} ·
                            @if($genderName || $typeName)
                                <span class="product-type">
                                    @if($genderName)
                                        {{ $genderName }}
                                    @endif

                                    @if($genderName && $typeName)
                                        |
                                    @endif

                                    @if($typeName)
                                        {{ $typeName }}
                                    @endif
                                </span>
                            @endif
                        </p>
                        <p class="pname">{{ $product->name }}</p>
                        <p class="price">{{ $firstVariant?->price }} ₼</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection
