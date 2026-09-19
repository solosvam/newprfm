<div class="home-banner">
    <div class="home-banner__wrap">
        <div class="banner-img">
            <img src="{{ asset('frontend/uploads/banners/' . $banners['topweb']) }}" alt="Top Banner" />
        </div>
        <!-- Close Button -->
        <button class="home-banner__close" onclick="closeBanner()">
            <img src="{{asset('frontend/images/filter-close.svg')}}" alt="Close" />
        </button>
    </div>
</div>

<!--HOME HEADING BANNER FOR MOBILE-->
<div class="home-banner-mobile">
    <div class="home-banner__wrap">
        <div class="banner-img">
            <img src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']) }}" alt="Top Banner" />
        </div>
    </div>
</div>
<!--HOME HEADING BANNER FOR MOBILE-->

