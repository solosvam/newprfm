{{-- AI ətir məsləhətçisi (AdvisorController, PerfumeAdvisorService): suallar → POST /advisor → kartlar + izah (frontend/js/advisor.js) --}}
@extends('frontend.layouts.app')

@php
    // sual => [tip, standart]
    $questions = [
        'for' => ['radio', 'women'],
        'occasion' => ['radio', 'daily'],
        'season' => ['radio', 'any'],
        'families' => ['checkbox', null],
        'strength' => ['radio', 'any'],
        'budget' => ['radio', 'any'],
    ];
    $labelKey = ['for' => 'advisor_for_', 'occasion' => 'advisor_occasion_', 'season' => 'advisor_season_', 'families' => 'advisor_family_', 'strength' => 'advisor_strength_', 'budget' => 'advisor_budget_'];
@endphp

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/advisor.css') }}">
@endsection

@section('title', __('advisor_title').' | Parfumshop.az')
@section('meta_description', __('advisor_meta'))
@section('og_title', __('advisor_title').' | Parfumshop.az')

@section('content')
    <main class="advisor">
        <header class="advisor-hero">
            <div class="advisor-hero__bot">
                <img src="{{ asset_v('frontend/images/advisor-bot-lg.webp') }}" alt="" width="180" height="180" decoding="async" fetchpriority="high">
            </div>
            <span class="advisor-hero__badge">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>
                AI
            </span>
            <h1 class="advisor-hero__title">{{ __('advisor_title') }}</h1>
            <p class="advisor-hero__text">{{ __('advisor_subtitle') }}</p>
        </header>

        <form class="advisor-form" id="advisorForm" action="{{ route('front.advisor.recommend') }}" method="POST" novalidate
              data-loading="{{ __('advisor_loading') }}" data-error="{{ __('advisor_error') }}">
            @csrf
            @foreach($questions as $name => [$type, $default])
                <fieldset class="advisor-q">
                    <legend class="advisor-q__title">
                        <span class="advisor-q__num">{{ $loop->iteration }}</span>
                        {{ __('advisor_q_'.$name) }}
                        @if($name === 'families')<small>{{ __('advisor_families_hint') }}</small>@endif
                    </legend>
                    <div class="advisor-q__options" @if($name === 'families') data-max="3" @endif>
                        @foreach($options[$name] as $value)
                            <label class="advisor-chip">
                                <input type="{{ $type }}" name="{{ $type === 'checkbox' ? $name.'[]' : $name }}" value="{{ $value }}" @checked($value === $default)>
                                <span>{{ __($labelKey[$name].$value) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <fieldset class="advisor-q">
                <legend class="advisor-q__title">
                    <span class="advisor-q__num">{{ count($questions) + 1 }}</span>
                    {{ __('advisor_q_liked') }} <small>{{ __('advisor_optional') }}</small>
                </legend>
                <input type="text" name="liked" maxlength="120" class="advisor-input" placeholder="{{ __('advisor_liked_placeholder') }}" autocomplete="off">
            </fieldset>

            <button type="submit" class="btn btn-dark advisor-submit">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg>
                {{ __('advisor_submit') }}
            </button>
        </form>

        {{-- AI işləyərkən (5–15 san): "düşünən" robot --}}
        <div class="advisor-thinking" id="advisorThinking" hidden aria-live="polite">
            <div class="advisor-thinking__bot">
                <img src="{{ asset_v('frontend/images/advisor-bot-lg.webp') }}" alt="" width="140" height="140">
            </div>
            <p class="advisor-thinking__text">{{ __('advisor_loading') }}</p>
            <span class="advisor-thinking__dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>

        <section class="advisor-results" id="advisorResults" hidden aria-live="polite" data-dynamic-cards>
            <div class="advisor-results__head">
                <h2 class="advisor-results__title">{{ __('advisor_results') }}</h2>
                <button type="button" class="btn btn-outline advisor-again" id="advisorAgain">{{ __('advisor_again') }}</button>
            </div>
            <p class="advisor-results__intro" id="advisorIntro"></p>
            <div class="advisor-results__body" id="advisorBody"></div>
            <p class="advisor-results__note">{{ __('advisor_disclaimer') }}</p>
        </section>
    </main>
@endsection

@section('page-scripts')
    <script defer src="{{ asset_v('frontend/js/advisor.js') }}"></script>
@endsection
