@extends('frontend.layouts.app')

@php
    $locale = app()->getLocale();
    $question = fn ($faq) => $faq->{'title_'.$locale} ?: $faq->title_az;
    $answer = fn ($faq) => $faq->{'content_'.$locale} ?: $faq->content_az;
    $structured = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqs->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $question($faq),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $answer($faq))],
        ])->values()->all(),
    ];
@endphp

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/info.css') }}">
@endsection

@section('title', __('faq_title').' | Parfumshop.az')
@section('meta_description', __('faq_title').' — Parfumshop.az')

@if($faqs->isNotEmpty())
    @section('structured_data')
        <script type="application/ld+json">@json($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
    @endsection
@endif

@section('content')
    <main class="info-page">
        <div class="info-layout">
            @include('frontend.partials.info-nav', ['active' => 'faq'])

            <article class="info-card">
                <h1 class="info-card__title">{{ __('faq_title') }}</h1>

                @forelse($faqs as $faq)
                    <details class="faq-item" @if($loop->first) open @endif>
                        <summary>{{ $question($faq) }}</summary>
                        <div class="faq-item__answer info-content">{!! \App\Models\Page::clean(nl2br((string) $answer($faq))) !!}</div>
                    </details>
                @empty
                    <p class="info-content">{{ __('faq_empty') }}</p>
                @endforelse

                <div class="faq-more">
                    <span>{{ __('faq_more') }}</span>
                    <a href="{{ route('front.page.contact') }}" class="btn btn-outline faq-more__btn">{{ __('faq_contact') }}</a>
                </div>
            </article>
        </div>
    </main>
@endsection
