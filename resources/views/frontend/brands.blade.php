{{--
  Brendlər (A–Z): axtarış, hərf zolağı, hərflərə görə brend kartları (loqo, ad, məhsul sayı).
  Loqo məntiqi ana səhifədəki brend zolağı ilə eynidir (BrandLogoService). JS: frontend/js/pages/brands.js
--}}
@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/brands.css') }}">
@endsection

@section('content')
    <main class="brands-page" data-brands-page>
        <header class="brands-head">
            <div>
                <h1>{{ __('brands') }}</h1>
                <p class="brands-head__count">{{ __('brands_count', ['count' => $total]) }}</p>
            </div>
            <label class="brands-search">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" placeholder="{{ __('brands_search_placeholder') }}" aria-label="{{ __('brands_search_placeholder') }}" autocomplete="off" data-brands-search>
            </label>
        </header>

        <nav class="brands-az" aria-label="{{ __('brands_letters') }}">
            @foreach($letters as $letter)
                @if($groups->has($letter))
                    <a href="#brands-{{ $loop->index }}" data-brands-letter="{{ $letter }}">{{ $letter }}</a>
                @else
                    <span aria-hidden="true">{{ $letter }}</span>
                @endif
            @endforeach
        </nav>

        <div class="brands-index">
            @foreach($letters as $letter)
                @continue(!$groups->has($letter))
                <section class="brands-letter" id="brands-{{ $loop->index }}" data-brands-group="{{ $letter }}">
                    <h2 class="brands-letter__title">{{ $letter }}</h2>
                    <div class="brands-letter__grid">
                        @foreach($groups[$letter] as $brand)
                            @php
                                $svgLogo = \App\Services\BrandLogoService::svgUrl($brand->image);
                                $logo = $svgLogo ? null : \App\Services\BrandLogoService::url($brand->image);
                            @endphp
                            <a class="brand-card brand-tile" href="{{ route('brand.products', ['slug' => $brand->slug]) }}"
                               data-brand-name="{{ mb_strtolower($brand->name) }}">
                                <span class="brand-tile__logo">
                                    @if($svgLogo)
                                        <span class="brand-logo" role="img" aria-hidden="true" style="--logo: url('{{ $svgLogo }}')"></span>
                                    @elseif($logo)
                                        <img src="{{ $logo }}" alt="" loading="lazy" decoding="async" draggable="false">
                                    @else
                                        <span class="brand-tile__initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($brand->name, 0, 1)) }}</span>
                                    @endif
                                </span>
                                <span class="brand-tile__name">{{ $brand->name }}</span>
                                <span class="brand-tile__count">{{ __('brands_products', ['count' => $brand->products_count]) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <p class="brands-empty" hidden data-brands-empty>{{ __('brands_empty') }}</p>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/brands.js') }}" defer></script>
@endsection
