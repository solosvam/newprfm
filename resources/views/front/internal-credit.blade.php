@extends('front.layout')
@section('content')
<main>
    <div class="container">
        <div class="internal-credit">
            <div class="breadcrumb">
                <ul>
                    <li>
                        <a href="{{route('home')}}">
                            <img src="{{asset('frontend/images/home.svg')}}" alt="" />
                        </a>
                    </li>
                    <li>
                        <img src="{{asset('frontend/images/arrow-right.svg')}}" alt="" />
                    </li>
                    <li>
                        <a href="{{route('internal-credit')}}"
                        >Hissə-hissə müraciət qaydaları</a
                        >
                    </li>
                </ul>
            </div>
            <div class="internal-credit__tabs">
                <h1>Hissə-hissə müraciət qaydaları</h1>
                <div class="accordion">
                    @foreach($faqs as $faq)
                    <div class="accordion-item">
                        <div class="accordion-header">
                            {{$faq->title_az}}
                            <span class="accordion-icon">+</span>
                        </div>
                        <div class="accordion-body">
                            {{$faq->content_az}}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="internal-credit__payment">
                <div class="internal-credit__payment__method">
                    <div class="text">
                        <a href=""
                        >Ödəniş edin <img src="{{asset('frontend/images/arrow-long.svg')}}" alt=""
                            /></a>
                        <p>
                            Daxili kredit ödənişlərinizi Milliön üzərindən həyata keçirə
                            bilərsiniz.
                        </p>
                    </div>
                    <div class="image">
                        <img src="{{asset('frontend/images/million.svg')}}" alt="" />
                    </div>
                </div>
                <div class="internal-credit__payment__method">
                    <div class="text">
                        <a href=""
                        >Ödəniş edin <img src="{{asset('frontend/images/arrow-long.svg')}}" alt=""
                            /></a>
                        <p>
                            Daxili kredit ödənişlərinizi Expresspay üzərindən həyata
                            keçirə bilərsiniz.
                        </p>
                    </div>
                    <div class="image">
                        <img src="{{asset('frontend/images/expresspay.svg')}}" alt="" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
@section('page-scripts')
    <script src="{{asset('frontend/js/accordion.js')}}"></script>
@endsection
