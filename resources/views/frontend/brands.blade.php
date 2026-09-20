@extends('frontend.layout')
@section('content')
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-for-brands-page">
            <ul>
                <li>
                    <a href="#" onclick="window.history.back()">
                        <img src="{{asset('frontend/images/arrow-left.svg')}}" alt="" />
                        <span>Geri qayıt</span>
                    </a>
                </li>
                <li>
                    <ul>
                        <li>
                            <a href="{{route('home')}}">
                                <img src="{{asset('frontend/images/home.svg')}}" alt="" />
                            </a>
                        </li>
                        <li>
                            <img src="{{asset('frontend/images/arrow-right.svg')}}" class="arrow" alt="" />
                        </li>
                        <li>
                            <a href="{{route('brands')}}">Markalar</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="home-page">
            @include('frontend.include.left')
            <div class="main-right">
                <div class="brands-section">
                    @foreach ($brands as $letter => $group)
                    <div class="brands-list">
                        <h2>{{ $letter }}</h2>
                        <ul>
                            @foreach ($group as $brand)
                                <li>
                                    <a href="{{ route('brand.products', \App\Services\SeoUrl::generateImageName(['id' => $brand->id, 'title' => $brand->name])) }}">
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
@endsection
