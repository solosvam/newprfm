@php
    // Əlaqə və sosial şəbəkələr — Admin → Ayarlar → Əlaqə məlumatları
    $contact = app(\App\Services\ContactInfo::class);
    $socials = $contact->socials();
    $socialIcons = [
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"/>',
        'youtube' => '<path d="M2.5 17a24 24 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.6 49.6 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24 24 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.6 49.6 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
    ];
@endphp
<footer>
    <div class="wrap footer-top">
        <div class="brand-col">
            <a href="{{ route('home') }}" class="logo logo--svg" aria-label="parfumshop">
                @include('frontend.partials.logo')
            </a>
            <p>{{ __('footer_intro') }}</p>
            @if($socials)
                <div class="fsocial">
                    @foreach($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ['youtube' => 'YouTube'][$network] ?? ucfirst($network) }}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $socialIcons[$network] !!}</svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h4>{{ __('common_categories') }}</h4>
            <ul>
                @foreach(\App\Models\Product\Category::where('active', 1)->orderBy('id')->get() as $category)
                    <li><a href="{{ route('category', ['slug' => $category->slug]) }}">{{ $category->{'name_' . app()->getLocale()} ?: $category->name_az }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h4>{{ __('footer_help') }}</h4>
            @php
                // Məlumat səhifələrinin başlıqları — admindən (Marketinq → Məlumat səhifələri)
                $infoTitles = \App\Models\Page::whereIn('key', ['delivery', 'returns', 'about', 'contact'])->get()->keyBy('key');
            @endphp
            <ul>
                @foreach(['delivery', 'returns', 'about', 'contact'] as $infoKey)
                    @if($infoPage = $infoTitles->get($infoKey))
                        <li><a href="{{ $infoPage->url() }}">{{ $infoPage->text('title') }}</a></li>
                    @endif
                    @if($infoKey === 'delivery')
                        <li><a href="{{ route('front.installment') }}">{{ __('page_installment_title') }}</a></li>
                        <li><a href="{{ route('front.bonus') }}">{{ __('page_bonus_title') }}</a></li>
                        <li><a href="{{ route('front.referral') }}">{{ __('referral_title') }}</a></li>
                    @endif
                @endforeach
                <li><a href="{{ route('front.faq') }}">{{ __('faq_title') }}</a></li>
                <li><a href="{{ auth()->check() ? route('profile') : route('front.login') }}">{{ __('footer_account') }}</a></li>
            </ul>
        </div>
        <div>
            <h4>{{ __('footer_contact') }}</h4>
            <ul>
                @if($hours = $contact->localized('hours'))<li>{{ $hours }}</li>@endif
                @if($contact->raw('contact_whatsapp'))
                    <li><a href="{{ $contact->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp: {{ $contact->raw('contact_whatsapp') }}</a></li>
                @endif
                @if($contact->raw('contact_phone'))
                    <li><a href="{{ $contact->phoneUrl() }}">{{ $contact->raw('contact_phone') }}</a></li>
                @endif
                @if($email = $contact->raw('contact_email'))
                    <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                @endif
                <li><a href="{{ route('front.page.contact') }}">{{ __('footer_contact_page') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <span>© {{ date('Y') }} parfumshop.az. {{ __('footer_rights') }}</span>
        <span class="footer-legal">
            <a href="{{ route('front.page.terms') }}">{{ __('footer_legal_terms') }}</a>
            <a href="{{ route('front.page.privacy') }}">{{ __('footer_legal_privacy') }}</a>
        </span>
        <div class="payment-icons">
            <span>VISA</span><span>Mastercard</span><span>M10</span><span>Birbank</span>
        </div>
    </div>
</footer>
