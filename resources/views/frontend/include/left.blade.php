<div class="main-left">
    <!-- Filter Form -->
    <div class="filter-section">
        <div class="filter-section-heading">
                  <span>
                    <img src="{{asset('frontend/images/filter.svg')}}" alt="">
                    <span> Filter</span>
                  </span>
            <img class="filter-section__close" src="{{asset('frontend/images/filter-close.svg')}}" alt="" />
        </div>
        <div class="filter-section__price">
            <h4>Qiymət aralığı</h4>
            <div class="price-range">
                <div class="slider-container">
                    <input type="range" id="min-range" min="40" max="2400" value="40" oninput="updateRangeValues()" />
                    <input type="range" id="max-range" min="40" max="2400" value="2400" oninput="updateRangeValues()" />
                </div>
                <div class="range-values">
                    <span id="min-value">40 AZN</span>
                    <span id="max-value">2400 AZN</span>
                </div>
            </div>
        </div>
        <div class="filter-section__checkbox">
            <h4>Ətirin növü</h4>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Parfum
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Extrait
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Toilette
            </label>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Cologne
            </label>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Cologne
            </label>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Eau de Cologne
            </label>
        </div>
        <div class="filter-section__checkbox">
            <h4>Ətirin həcmi</h4>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                25-50 ml
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                50-100 ml
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                100-150 ml
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                150-200 ml
            </label>
        </div>
        <div class="filter-section__checkbox" style="margin-bottom: 0;">
            <h4>Qoxu qrupu</h4>
            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Fujer
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Şipr
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Çiçək
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Dəri
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Şərq
            </label>

            <label class="custom-checkbox">
                <input type="checkbox" />
                <span class="checkmark"></span>
                Ağac
            </label>
        </div>
    </div>
    <!-- Filter Form -->
    <div class="main-left__links">
        <ul>
            <li class="left-links-filter">
                <img src="{{asset('frontend/images/filter.svg')}}" alt="filter icon" />
                <span>{{__('filter')}}</span>
            </li>
            <li class="left-links-brands">
                <img src="{{asset('frontend/images/brands.svg')}}" alt="brand icon" />
                <a href="{{route('brands')}}"> <span>{{__('brands')}}</span></a>
            </li>
            <li>
                <img src="{{asset('frontend/images/gift_card.svg')}}" alt="gift icon" />
                <span>{{__('gift_card')}}</span>
            </li>
            <li class="terms-link">
                <img src="{{asset('frontend/images/terms.svg')}}" alt="terms icon" />
                <span>{{__('rules')}}</span>
            </li>
        </ul>
        <div class="internal-credit-btn">
            <a href="{{route('internal-credit')}}">{{__('internal_credit')}}</a>
        </div>
    </div>
    <div class="best-seller">
        <h1>{{__('best_seller')}}</h1>
        <ul>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
            <li>
                <div class="img">
                    <img src="{{asset('frontend/images/products/parfum.png')}}" alt="" />
                </div>
                <span>Mont Blanc Explorer</span>
            </li>
        </ul>
    </div>
    <div class="invite-friend">
        <h1>Dostunu dəvət et və 17azn bonus qazan! Dəvət göndər</h1>
        <img src="{{asset('frontend/images/invite.png')}}" alt="" />
        <button>Dəvət göndər</button>
    </div>
</div>
