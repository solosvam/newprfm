<footer>
    <div class="wrap footer-top">
        <div class="brand-col">
            <p class="logo2">parfumshop</p>
            <p>25 ilin təcrübəsi ilə xidmətinizdə olmaqdan qürur duyuruq. Həyatınızın ən ətirli səhifəsi bizimlə başlayır.</p>
            <div class="fsocial">
                <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/></svg></span>
                <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"/></svg></span>
                <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg></span>
            </div>
        </div>
        <div>
            <h4>Kateqoriyalar</h4>
            <ul>
                @foreach(\App\Models\Product\Category::where('active', 1)->orderBy('id')->get() as $category)
                    <li><a href="{{ route('home', ['category' => $category->id]) }}">{{ $category->{'name_' . app()->getLocale()} ?: $category->name_az }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4>Kömək</h4>
            <ul>
                <li><a href="{{ route('cart') }}">Səbət</a></li>
                <li><a href="{{ route('internal-credit') }}">Hissə-hissə ödəniş</a></li>
                <li><a href="{{ route('brands') }}">Brendlər</a></li>
                <li><a href="{{ auth()->check() ? route('profile') : route('front.login') }}">Şəxsi kabinet</a></li>
            </ul>
        </div>
        <div>
            <h4>Əlaqə</h4>
            <ul>
                <li>9:00–19:00, B.e.–Ş.</li>
                <li>(055) 55 10 700</li>
                <li>(012) 310 22 55</li>
                <li>info@parfumshop.az</li>
            </ul>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <span>© {{ date('Y') }} parfumshop.az. Bütün hüquqlar qorunur.</span>
        <div class="payment-icons">
            <span>VISA</span><span>Mastercard</span><span>M10</span><span>Birbank</span>
        </div>
    </div>
</footer>
