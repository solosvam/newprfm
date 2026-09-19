@extends('frontend.layout')
@section('content')
    <main id="product-details">
        <div class="container">
            <div class="details-page">
                <div class="breadcrumb">
                    <ul>
                        <li>
                            <a href="index.html">
                                <img src="{{asset('frontend/images/home.svg')}}" alt="" />
                            </a>
                        </li>
                        <li>
                            <img src="{{asset('frontend/images/arrow-right.svg')}}" alt="" />
                        </li>
                        <li>
                            <a href="">Qadın ətirləri</a>
                        </li>
                        <li>
                            <img src="{{asset('frontend/images/arrow-right.svg')}}" alt="" />
                        </li>
                        <li>
                            <a href="">Narciso Rodriguez</a>
                        </li>
                        <li>
                            <img src="{{asset('frontend/images/arrow-right.svg')}}" alt="" />
                        </li>
                        <li>
                            <a href="">Narciso Poudree</a>
                        </li>
                    </ul>
                </div>
                <div class="product-title">
                    <h1>{{$product->name}}</h1>
                    <span>{{$product->brand->name}}</span>
                    <span>{{$product->genders[0]->name_az}} | {{$product->type->name_az}}</span>
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
                            <img class="main-img" src="{{ asset('frontend/uploads/products/' . $product->images[0]->image) }}" alt=""/>
                        </div>
                    </div>
                    <div class="product-info">
                        <h1>
                            {{$product->sizes[0]->pivot->price}} <img src="{{asset('frontend/images/manat.svg')}}" alt="manat symbol" />
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
                                @foreach($product->sizes as $size)
                                    <li class="{{ $loop->first ? 'active-size-amount' : '' }}">
                                        <span>{{$size->name_az}}</span>
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
                            <button>Səbətə at</button>
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
                                <h1>926.66 AZN x 6 ay</h1>
                                <p>
                                    Birbank taksit kartı ilə 2, 3 və ya 6 aylıq faizsiz ödə!
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
                                <tbody>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" checked />
                                    </td>
                                    <td>3 ay</td>
                                    <td>14.45 ₼</td>
                                    <td>102 ₼</td>
                                </tr>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" />
                                    </td>
                                    <td>6 ay</td>
                                    <td>18.03 ₼</td>
                                    <td>167 ₼</td>
                                </tr>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" />
                                    </td>
                                    <td>9 ay</td>
                                    <td>22.67 ₼</td>
                                    <td>199 ₼</td>
                                </tr>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" />
                                    </td>
                                    <td>10 ay</td>
                                    <td>34.51 ₼</td>
                                    <td>215 ₼</td>
                                </tr>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" />
                                    </td>
                                    <td>12 ay</td>
                                    <td>43.76 ₼</td>
                                    <td>236 ₼</td>
                                </tr>
                                <tr>
                                    <td class="radio-cell">
                                        <input type="radio" name="duration" />
                                    </td>
                                    <td>18 ay</td>
                                    <td>54.12 ₼</td>
                                    <td>275 ₼</td>
                                </tr>
                                </tbody>
                            </table>
                            <button>Müraciət et</button>
                        </div>
                    </div>
                </div>
                <div class="details-tab">
                    <div class="tablinks">
                        <button class="active">Ətir haqqında</button>
                        <button>Rəylər <span>24</span></button>
                    </div>
                    <div class="tabcontents">
                        <div class="content1">
                <span
                >Narciso Rodriguez tərəfindən hazırlanmış Narciso Eau de
                  Parfum Poudree, qadınlar üçün şərq çiçəkli bir ətirdir.
                </span>
                            <span
                            >Bu yeni bir ətirdir. Narciso Eau de Parfum Ambrée 2020 -ci
                  ildə satışa çıxarılmışdır.
                </span>
                            <span
                            >Ətirçi: Aurelien Guichard. Üst notlar: Frangipani,
                  Ylang-ilanq və Ağ çiçəklər; orta notlar: Musk və Ambergris;
                  əsas notlar: Cashmeran, Vanilla və Cedar.
                </span>
                        </div>
                        <div class="content2">
                            <h4><span>Rəylər</span> Narciso Poudree Narciso Rodriguez</h4>
                            <div class="ratings-container">
                                <div class="rating-summary">
                                    <div class="average-rating">
                                        <span class="rating-value">4.8</span>
                                        <div class="stars">
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                            <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                        </div>
                                    </div>
                                    <button class="write-review">Rəy yaz</button>
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
                                <div class="header-icon">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 448 512"
                                    >
                                        <path
                                            d="M201.4 137.4c12.5-12.5 32.8-12.5 45.3 0l160 160c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L224 205.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l160-160z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="dropdown-options" id="dropdown-options">
                                <div class="option selected">
                                    <svg
                                        class="check-icon"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 448 512"
                                    >
                                        <path
                                            d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"
                                        />
                                    </svg>
                                    Son raylor
                                </div>
                                <div class="option">
                                    <svg
                                        class="check-icon"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 448 512"
                                    >
                                        <path
                                            d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"
                                        /></svg
                                    >Əvvəlki rəylər
                                </div>
                                <div class="option">
                                    <svg
                                        class="check-icon"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 448 512"
                                    >
                                        <path
                                            d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"
                                        /></svg
                                    >Müsbət rəylər
                                </div>
                                <div class="option">
                                    <svg
                                        class="check-icon"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 448 512"
                                    >
                                        <path
                                            d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"
                                        /></svg
                                    >Mənfi rəylər
                                </div>
                            </div>
                        </div>
                        <hr />
                        <div class="review">
                            <p>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed
                                do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </p>
                            <div class="review-info">
                                <div class="stars">
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                </div>
                                <span class="name">Rövşən Məmmədov</span>
                                <span class="date">4 avqust, 2021</span>
                            </div>
                        </div>
                        <div class="review">
                            <p>
                                Ut enim ad minim veniam, quis nostrud exercitation ullamco
                                laboris nisi ut aliquip ex ea commodo consequat. Duis aute
                                irure dolor in reprehenderit in voluptate velit esse cillum
                                dolore eu fugiat nulla pariatur.
                            </p>
                            <div class="review-info">
                                <div class="stars">
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-outlined.svg')}}" alt="" />
                                </div>
                                <span class="name">Babək Əliyev</span>
                                <span class="date">21 iyul, 2021</span>
                            </div>
                        </div>
                        <div class="review">
                            <p>
                                Ut enim ad minim veniam, quis nostrud exercitation ullamco
                                laboris nisi ut aliquip ex ea commodo consequat. Duis aute
                                irure dolor in reprehenderit in voluptate velit esse cillum
                                dolore eu fugiat nulla pariatur.
                            </p>
                            <div class="review-info">
                                <div class="stars">
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/star-filled.svg')}}" alt="" />
                                </div>
                                <span class="name">Aqşin Söhraбоğlu</span>
                                <span class="date">16 mart, 2021</span>
                            </div>
                        </div>
                        <button class="load-more">Daha çox</button>
                    </div>
                </div>
                <div class="similar-products">
                    <h1 class="similar-products__title">Bənzər məhsullar</h1>
                    <div class="similar-products__list">
                        <div class="product-item">
                            <div class="product-item__image">
                                <div class="product-item__image__actions">
                                    <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                                </div>
                                <a href="product-details.html">
                                    <img
                                        class="product-main-image"
                                        src="{{asset('frontend/images/products/parfum.png')}}"
                                        alt="product image"
                                    />
                                </a>
                            </div>
                            <div class="product-item__info">
                                <div class="title">
                                    <h1>My Burberry</h1>
                                    <span>Burberry</span>
                                    <span>Kişi üçün | Eau De Parfum</span>
                                    <span class="product-price"
                                    >50 ml / <span>96.00 ₼ </span></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="product-item">
                            <div class="product-item__image">
                                <div class="product-item__image__actions">
                                    <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                                </div>
                                <a href="product-details.html">
                                    <img
                                        class="product-main-image"
                                        src="{{asset('frontend/images/products/parfum.png')}}"
                                        alt="product image"
                                    />
                                </a>
                            </div>
                            <div class="product-item__info">
                                <div class="title">
                                    <h1>My Burberry</h1>
                                    <span>Burberry</span>
                                    <span>Kişi üçün | Eau De Parfum</span>
                                    <span class="product-price"
                                    >50 ml / <span>96.00 ₼ </span></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="product-item">
                            <div class="product-item__image">
                                <div class="product-item__image__actions">
                                    <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                                </div>
                                <a href="product-details.html">
                                    <img
                                        class="product-main-image"
                                        src="{{asset('frontend/images/products/parfum.png')}}"
                                        alt="product image"
                                    />
                                </a>
                            </div>
                            <div class="product-item__info">
                                <div class="title">
                                    <h1>My Burberry</h1>
                                    <span>Burberry</span>
                                    <span>Kişi üçün | Eau De Parfum</span>
                                    <span class="product-price"
                                    >50 ml / <span>96.00 ₼ </span></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="product-item">
                            <div class="product-item__image">
                                <div class="product-item__image__actions">
                                    <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                                </div>
                                <a href="product-details.html">
                                    <img
                                        class="product-main-image"
                                        src="{{asset('frontend/images/products/parfum.png')}}"
                                        alt="product image"
                                    />
                                </a>
                            </div>
                            <div class="product-item__info">
                                <div class="title">
                                    <h1>My Burberry</h1>
                                    <span>Burberry</span>
                                    <span>Kişi üçün | Eau De Parfum</span>
                                    <span class="product-price"
                                    >50 ml / <span>96.00 ₼ </span></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="product-item">
                            <div class="product-item__image">
                                <div class="product-item__image__actions">
                                    <img src="{{asset('frontend/images/share.svg')}}" alt="" />
                                    <img src="{{asset('frontend/images/product-card-wishlist.svg')}}" alt="" />
                                </div>
                                <a href="product-details.html">
                                    <img
                                        class="product-main-image"
                                        src="{{asset('frontend/images/products/parfum.png')}}"
                                        alt="product image"
                                    />
                                </a>
                            </div>
                            <div class="product-item__info">
                                <div class="title">
                                    <h1>My Burberry</h1>
                                    <span>Burberry</span>
                                    <span>Kişi üçün | Eau De Parfum</span>
                                    <span class="product-price"
                                    >50 ml / <span>96.00 ₼ </span></span
                                    >
                                </div>
                            </div>
                        </div>
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
    <script src="{{asset('frontend/js/product.js?v=1.1.1')}}"></script>
@endsection
