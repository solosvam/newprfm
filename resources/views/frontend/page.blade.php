@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/info.css') }}">
@endsection

@section('title', $page->text('title').' | Parfumshop.az')
@section('meta_description', $page->text('meta_description') ?: $page->text('title'))
@section('og_title', $page->text('title').' | Parfumshop.az')

@section('content')
    <main class="info-page">
        <div class="info-layout">
            @include('frontend.partials.info-nav', ['active' => $page->key])

            <article class="info-card">
                <h1 class="info-card__title">{{ $page->text('title') }}</h1>

                @if($page->key === 'delivery')
                    {{-- ayarlardan: həmişə saytdakı real qaydaya uyğun --}}
                    <div class="info-facts">
                        <div class="info-fact">
                            <div class="info-fact__label">{{ __('page_delivery_fee') }}</div>
                            <div class="info-fact__value">
                                @if($delivery['mode'] === 'free' || (float) $delivery['fee'] <= 0)
                                    {{ __('page_delivery_free') }}
                                @else
                                    {{ rtrim(rtrim(number_format((float) $delivery['fee'], 2, '.', ''), '0'), '.') }} ₼
                                @endif
                            </div>
                            @if($delivery['mode'] !== 'free' && (float) $delivery['fee'] > 0 && $delivery['free_from'] > 0)
                                <div class="info-fact__hint">{{ __('page_delivery_free_from', ['amount' => rtrim(rtrim(number_format((float) $delivery['free_from'], 2, '.', ''), '0'), '.')]) }}</div>
                            @endif
                        </div>
                        <div class="info-fact">
                            <div class="info-fact__label">{{ __('page_payment_methods') }}</div>
                            <ul class="info-fact__list">
                                @foreach($paymentMethods as $method)
                                    <li>{{ $method->{'name_'.app()->getLocale()} ?: $method->name_az }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($page->key === 'contact')
                    {{-- Admin → Ayarlar → Əlaqə məlumatları --}}
                    @php $contact = app(\App\Services\ContactInfo::class); @endphp
                    <div class="contact-cards">
                        @if($phone = $contact->raw('contact_phone'))
                            <a class="contact-card" href="{{ $contact->phoneUrl() }}">
                                <span class="contact-card__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z"/></svg></span>
                                <span class="contact-card__label">{{ __('contact_phone') }}</span>
                                <span class="contact-card__value">{{ $phone }}</span>
                            </a>
                        @endif
                        @if($whatsapp = $contact->raw('contact_whatsapp'))
                            <a class="contact-card" href="{{ $contact->whatsappUrl() }}" target="_blank" rel="noopener">
                                <span class="contact-card__icon contact-card__icon--wa"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.5A8.4 8.4 0 1 1 21 11.5Z"/></svg></span>
                                <span class="contact-card__label">{{ __('contact_whatsapp') }}</span>
                                <span class="contact-card__value">{{ $whatsapp }}</span>
                            </a>
                        @endif
                        @if($email = $contact->raw('contact_email'))
                            <a class="contact-card" href="mailto:{{ $email }}">
                                <span class="contact-card__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg></span>
                                <span class="contact-card__label">{{ __('contact_email') }}</span>
                                <span class="contact-card__value">{{ $email }}</span>
                            </a>
                        @endif
                        @if($hours = $contact->localized('hours'))
                            <div class="contact-card">
                                <span class="contact-card__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                                <span class="contact-card__label">{{ __('contact_hours') }}</span>
                                <span class="contact-card__value">{{ $hours }}</span>
                            </div>
                        @endif
                        @if($address = $contact->localized('address'))
                            <div class="contact-card">
                                <span class="contact-card__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
                                <span class="contact-card__label">{{ __('contact_address') }}</span>
                                <span class="contact-card__value">{{ $address }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="info-content">{!! \App\Models\Page::clean($page->text('body')) !!}</div>

                @if(in_array($page->key, ['terms', 'privacy'], true))
                    <p class="info-updated">{{ __('page_updated', ['date' => $page->updated_at->format('d.m.Y')]) }}</p>
                @endif
            </article>
        </div>
    </main>
@endsection
