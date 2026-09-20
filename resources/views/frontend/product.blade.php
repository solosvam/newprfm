@extends('frontend.layout')
@section('page-styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/pages/product.css?v=' . filemtime(public_path('frontend/css/pages/product.css'))) }}">
@endsection
@section('content')
    @php
        $locale = app()->getLocale();
        $gender = $product->genders->first();
        $firstImage = $product->images->first();
        $variants = $product->variants->where('active', 1)->sortBy('price')->values();
        $firstVariant = $variants->first();
        $genderName = $gender ? ($gender->{'name_' . $locale} ?? $gender->name_az) : null;
        $typeName = $product->type ? ($product->type->{'name_' . $locale} ?? $product->type->name_az) : null;
        $initialPrice = (float) ($firstVariant?->price ?? 0);
        // Birbank taksit kartı ayrıca 6 ay faizsizdir.
        // Admin paneldəki kredit faizləri yalnız aşağıdakı kredit cədvəlinə tətbiq olunur.
        $birbankMonth = 6;
        $birbankTotal = $initialPrice;
    @endphp
    <main id="product-details">
        <div class="container">
            <div class="details-page">
                <div class="breadcrumb">
                    <ul>
                        <li>
                            <a href="{{ route('home') }}">
                                <img src="{{ asset('frontend/images/home.svg') }}" alt="Ana səhifə" />
                            </a>
                        </li>
                        <li><img src="{{ asset('frontend/images/arrow-right.svg') }}" alt="" /></li>
                        @if($product->brand)
                            <li>
                                <a href="{{ route('brand.products', \App\Services\SeoUrl::generateImageName(['id' => $product->brand->id, 'title' => $product->brand->name])) }}">{{ $product->brand->name }}</a>
                            </li>
                            <li><img src="{{ asset('frontend/images/arrow-right.svg') }}" alt="" /></li>
                        @endif
                        <li class="current"><span>{{ $product->name }}</span></li>
                    </ul>
                </div>
                <div class="product-title">
                    <h1>{{$product->name}}</h1>
                    <span>{{$product->brand->name}}</span>
                    <span>{{ $genderName }}{{ $genderName && $typeName ? ' | ' : '' }}{{ $typeName }}</span>
                </div>
                <div class="details-wrap">
                    <div class="product-image">
                        <ul class="left">
                            @foreach($product->images as $image)
                            <li>
                                <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="" />
                            </li>
                            @endforeach
                        </ul>
                        <div class="main">
                            <div class="main__actions">
                                <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                            </div>
                            @if($firstImage)
                                <img class="main-img" src="{{ asset('frontend/uploads/products/' . $firstImage->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}"/>
                            @endif
                        </div>
                    </div>
                    <div class="product-info">
                        <h1>
                            {{ number_format($firstVariant?->price ?? 0, 2) }} <img src="{{asset('frontend/images/manat.svg')}}" alt="manat symbol" />
                        </h1>
                        <div class="product-stars">
                            <ul>
                                <li>
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                </li>
                                <li>
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                </li>
                                <li>
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                </li>
                                <li>
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                </li>
                                <li>
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                </li>
                            </ul>
                        </div>
                        <div class="product-size-amount">
                            <ul>
                                @foreach($variants as $variant)
                                    <li class="{{ $loop->first ? 'active-size-amount' : '' }}"
                                        data-variant-id="{{ $variant->id }}"
                                        data-price="{{ $variant->price }}">
                                        <span>{{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="amount">
                                <small>Miqdar</small>
                                <div class="amount-input">
                                    <span id="decrease">-</span>
                                    <span class="count">1</span>
                                    <span id="increase">+</span>
                                </div>
                            </div>
                        </div>
                        <div class="product-info-actions">
                            <button type="button" id="addToCartButton" data-product-id="{{ $product->id }}">Səbətə at</button>
                            <div>
                                <span></span>
                                <p>və ya</p>
                                <span></span>
                            </div>
                            <button>1 kliklə sifariş</button>
                        </div>
                        <div class="product-shipping-info">
                            <img
                                class="shipping-info-icon"
                                src="{{asset('frontend/images/info.svg')}}"
                                alt=""
                            />
                            <span
                            >Bakı şəhər daxili çatdırılma ödənişsizdir
                  <span class="shipping-tooltip"
                  >Ünvandan asılı olaraq çatdırılma 30 dəqiqədən 2 saata qədər
                    dəyişə bilər</span
                  >
                </span>
                        </div>
                    </div>
                    <div class="product-taksit-table">
                        <div class="birbank-banner">
                            <img src="{{asset('frontend/images/birbank.png')}}" alt="" />
                            <div>
                                <h1><span id="birbankMonthly">{{ number_format($birbankTotal / $birbankMonth, 2) }}</span> AZN x <span id="birbankMonth">{{ $birbankMonth }}</span> ay</h1>
                                <p>
                                    Birbank taksit kartı ilə aktiv kredit müddətlərindən birini seçərək ödə!
                                </p>
                            </div>
                        </div>
                        <div class="taksit-table">
                            <table class="table-container">
                                <thead>
                                <tr>
                                    <th></th>
                                    <th>Müddət</th>
                                    <th>Ayda</th>
                                    <th>Qiymət</th>
                                </tr>
                                </thead>
                                <tbody id="installmentRows">
                                @foreach($creditPeriods as $period)
                                    @php
                                        $rate = (float) $period->interest_rate;
                                        $installmentTotal = $initialPrice + (($initialPrice * $rate) / 100);
                                        $installmentMonthly = $installmentTotal / $period->month;
                                    @endphp
                                    <tr data-month="{{ $period->month }}" data-rate="{{ $rate }}">
                                        <td class="radio-cell">
                                            <input type="radio" name="duration" value="{{ $period->month }}" {{ $loop->first ? 'checked' : '' }} />
                                        </td>
                                        <td>{{ $period->month }} ay</td>
                                        <td class="installment-monthly">{{ number_format($installmentMonthly, 2) }} ₼</td>
                                        <td class="installment-total">{{ number_format($installmentTotal, 2) }} ₼</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <button>Müraciət et</button>
                        </div>
                    </div>
                </div>
                <div class="details-tab">
                    <div class="tablinks">
                        <button class="active">Ətir haqqında</button>
                        <button>Rəylər <span>{{ $product->reviews->count() }}</span></button>
                    </div>
                    <div class="tabcontents">
                        <div class="content1">
                            {!! nl2br(e($product->{'content_' . $locale} ?: $product->content_az)) !!}
                        </div>
                        <div class="content2">
                            <h4><span>Rəylər</span> Narciso Poudree Narciso Rodriguez</h4>
                            <div class="ratings-container">
                                <div class="rating-summary">
                                    <div class="average-rating">
                                        <span class="rating-value">{{ number_format($ratingAverage, 1) }}</span>
                                        <div class="stars">
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                        </div>
                                    </div>
                                    <button class="write-review" type="button">Rəy yaz</button>
                                </div>
                                <div class="rating-distribution">
                                    <div class="rating-bar">
                                        <div class="progress-bar">
                                            <div class="filled" style="width: 90%"></div>
                                        </div>
                                        <span class="rating-count">18</span>
                                        <div class="stars-label">
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                        </div>
                                    </div>
                                    <div class="rating-bar">
                                        <div class="progress-bar">
                                            <div class="filled" style="width: 20%"></div>
                                        </div>
                                        <span class="rating-count">4</span>
                                        <div class="stars-label">
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                        </div>
                                    </div>
                                    <div class="rating-bar">
                                        <div class="progress-bar">
                                            <div class="filled" style="width: 10%"></div>
                                        </div>
                                        <span class="rating-count">2</span>
                                        <div class="stars-label">
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="reviews-container">
                        <div class="dropdown-container">
                            <div class="header" id="dropdown-header">
                                <h3>Son rəylər</h3>
                                <div class="header-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M201.4 137.4c12.5-12.5 32.8-12.5 45.3 0l160 160c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L224 205.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l160-160z"/></svg></div>
                            </div>
                        </div>
                        <hr>
                        <div id="reviewsList">
                            @forelse($product->reviews as $review)
                                <div class="review product-review {{ $loop->index >= 3 ? 'review-hidden' : '' }}">
                                    <p>{{ $review->comment }}</p>
                                    <div class="review-info">
                                        <div class="stars">
                                            @for($star = 1; $star <= 5; $star++)
                                                <img src="{{ asset('frontend/images/' . ($star <= $review->rating ? 'star-filled.svg' : 'star-outlined.svg')) }}" alt="">
                                            @endfor
                                        </div>
                                        <span class="name">{{ $review->customer?->name ?? 'Müştəri' }}</span>
                                        <span class="date">{{ $review->created_at->format('d.m.Y') }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="no-reviews">Bu məhsula hələ rəy yazılmayıb.</p>
                            @endforelse
                        </div>
                        @if($product->reviews->count() > 3)
                            <button class="load-more" type="button" id="loadMoreReviews">Daha çox</button>
                        @endif
                    </div>
                </div>

                <div class="similar-products">
                    <h1 class="similar-products__title">Bənzər məhsullar</h1>
                    <div class="similar-products__list">
                        @forelse($similarProducts as $similar)
                            @php
                                $similarImage = $similar->images->first();
                                $similarVariant = $similar->variants->first();
                                $similarGender = $similar->genders->first();
                                $similarGenderName = $similarGender ? ($similarGender->{'name_' . $locale} ?? $similarGender->name_az) : null;
                                $similarTypeName = $similar->type ? ($similar->type->{'name_' . $locale} ?? $similar->type->name_az) : null;
                            @endphp
                            <div class="product-item">
                                <div class="product-item__image">
                                    <div class="product-item__image__actions">
                                        <img src="{{ asset('frontend/images/share.svg') }}" alt="">
                                        <img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt="">
                                    </div>
                                    <a href="{{ route('product', $similar->slug) }}">
                                        @if($similarImage)
                                            <img class="product-main-image" src="{{ asset('frontend/uploads/products/' . $similarImage->image) }}" alt="{{ $similar->brand?->name }} {{ $similar->name }}">
                                        @endif
                                    </a>
                                </div>
                                <div class="product-item__info">
                                    <div class="title">
                                        <h1><a href="{{ route('product', $similar->slug) }}">{{ $similar->name }}</a></h1>
                                        <span>{{ $similar->brand?->name }}</span>
                                        <span>{{ $similarGenderName }}{{ $similarGenderName && $similarTypeName ? ' | ' : '' }}{{ $similarTypeName }}</span>
                                        @if($similarVariant)
                                            <span class="product-price">{{ $similarVariant->size?->{'name_' . $locale} ?? $similarVariant->size?->name_az }} / <span>{{ number_format($similarVariant->price, 2) }} ₼</span></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p>Oxşar tərkibli məhsul tapılmadı.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
@section('modal')


    <!--OTHER SIZES FOR MOBILE-->
    <div class="other-sizes-container-mobile">
        <div class="other-sizes-overlay"></div>
        <div class="other-sizes-content">
            <div class="other-sizes-heading">
                <img src="{{asset('frontend/images/close.svg')}}" alt="">
                <span>Digər ölçülər</span>
            </div>

            <div class="other-sizes-body">
                <ul>
                    <li>
                        30 ml / <span>66.00 &#8380;</span>
                    </li>
                    <li>
                        50 ml / <span> 96.00 &#8380; </span>
                    </li>
                    <li>
                        90 ml / <span>218.00 &#8380;</span>
                    </li>
                    <li>
                        110 ml / <span>251.00 &#8380;</span>
                    </li>
                    <li>
                        130 ml / <span>294.00 &#8380;</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!--OTHER SIZES FOR MOBILE-->

    <div id="reviewModal" class="review-modal">
        <div class="review-modal__box">
            <button type="button" class="review-modal__close">&times;</button>
            <h3>Rəy yaz</h3>
            @auth
                <form method="POST" action="{{ route('product.review', $product->id) }}">
                    @csrf
                    <div class="review-rating">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" id="rating{{ $i }}" name="rating" value="{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }}>
                            <label for="rating{{ $i }}">★</label>
                        @endfor
                    </div>
                    <textarea name="comment" rows="5" maxlength="2000" placeholder="Məhsul haqqında fikrinizi yazın..." required>{{ old('comment') }}</textarea>
                    @error('rating')<small>{{ $message }}</small>@enderror
                    @error('comment')<small>{{ $message }}</small>@enderror
                    <button type="submit">Rəyi göndər</button>
                </form>
            @else
                <p>Rəy yazmaq üçün hesabınıza daxil olun.</p>
                <a class="review-login" href="{{ route('front.login') }}">Daxil ol</a>
            @endauth
        </div>
    </div>

    <!-- Pay by click Modal -->
    <div id="payByClickModal" class="pay-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close pay-modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <h1>Sifarişi tamamlamaq üçün mobil nömrənizi daxil edin</h1>
                <input type="tel" placeholder="Mobil nömrənizi qeyd edin" />
                <div class="alert">
                    <img src="{{asset('frontend/images/info.svg')}}" alt="" />
                    <p>
                        Diqqət ! Hörmətli müştərilər Bir kliklə sifariş sadəcə Nağd və
                        Plastik kartla olan sifarişlər üçündür. Hissə hissə ödəniş
                        müraciəti üçün "Müraciət et" düyməsini sıxın.
                    </p>
                </div>
                <button>Müraciət et</button>
            </div>
        </div>
    </div>
    <!-- Pay by click Modal -->
@endsection
@section('page-scripts')
    <script src="{{ asset('frontend/js/product.js?v=' . filemtime(public_path('frontend/js/product.js'))) }}"></script>
@endsection
