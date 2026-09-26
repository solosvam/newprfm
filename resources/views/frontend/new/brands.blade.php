@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.brands-left')

                <div>
                    <div class="section-head">
                        <h1>{{ __('reviews_brands') }}</h1>
                    </div>

                    <div class="brand-groups">
                        @foreach ($brands as $letter => $group)
                            <div class="brand-group">
                                <span class="brand-group__letter">{{ $letter }}</span>
                                <ul class="brand-group__list">
                                    @foreach ($group as $brand)
                                        <li>
                                            <a href="{{ route('brand.products', $brand->slug) }}">
                                                {{ $brand->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
