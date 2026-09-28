{{--
    Parametrlər:
    $title        — h1 başlığı
    $total        — məhsul sayı
    $crumbs       — [['label' => ..., 'url' => ...], ...]
    $image        — loqo/şəkil URL-i (optional)
    $description  — qısa təsvir (optional)
    $hideBrand    — kartlarda brend adını gizlət (brend səhifəsi üçün)
--}}

<section class="catalog-hero">
    <nav class="catalog-hero__crumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">{{ __('product_home') }}</a>
        @foreach($crumbs ?? [] as $crumb)
            <span aria-hidden="true">/</span>
            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
        @endforeach
    </nav>

    <div class="catalog-hero__main">
        @if(!empty($image))
            <div class="catalog-hero__logo">
                <img src="{{ $image }}" alt="{{ $title }}" loading="eager">
            </div>
        @endif

        <div class="catalog-hero__text">
            <h1 class="catalog-hero__title">{{ $title }}</h1>
            <p class="catalog-hero__count">{{ trans_choice('catalog_products_count', $total, ['count' => $total]) }}</p>
        </div>
    </div>

    @if(!empty($description))
        <p class="catalog-hero__desc">{{ \Illuminate\Support\Str::limit(strip_tags($description), 280) }}</p>
    @endif
</section>

<div class="layout">
    @include('frontend.includes.catalog-sidebar', ['showExtras' => false])

    <div>
        @include('frontend.includes.catalog-toolbar')

        @if($products->isEmpty())
            <p class="catalog-empty">{{ __('catalog_no_products') }}</p>
        @else
            <div class="grid">
                @foreach ($products as $product)
                    @include('frontend.includes.product-card', [
                        'product'   => $product,
                        'hideBrand' => $hideBrand ?? false,
                    ])
                @endforeach
            </div>
        @endif
    </div>
</div>

{{ $products->links('frontend.includes.pagination') }}
