{{--
  AI ətir məsləhətçisi — chat (AdvisorController, PerfumeAdvisorService, frontend/js/advisor.js).
  İlk iki mesaj (salam + "kimin üçündür?") burada hazırdır; sonrakı sualları AI cavaba görə yazır.
  Söhbət brauzerin localStorage-ində saxlanır (serverdə yox).
--}}
@extends('frontend.layouts.app')

@php
    $start = [
        ['role' => 'assistant', 'text' => __('advisor_speech')],
        ['role' => 'assistant', 'text' => __('advisor_q_for'), 'replies' => [__('advisor_for_women'), __('advisor_for_men'), __('advisor_for_any')]],
    ];
    $bot = asset_v('frontend/images/advisor-bot-lg.webp');
@endphp

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/advisor.css') }}">
@endsection

@section('title', __('advisor_title').' | Parfumshop.az')
@section('meta_description', __('advisor_meta'))
@section('og_title', __('advisor_title').' | Parfumshop.az')

@section('content')
    <main class="advisor advisor--chat">
        <header class="advisor-head">
            <div class="advisor-head__bot">
                <img src="{{ $bot }}" alt="" width="64" height="64" decoding="async" fetchpriority="high">
                <span class="advisor-head__badge">AI</span>
            </div>
            <div class="advisor-head__text">
                <h1 class="advisor-head__title">{{ __('advisor_greeting') }}</h1>
                <span class="advisor-head__status">{{ __('advisor_title') }}</span>
            </div>
            <button type="button" class="advisor-restart" id="advisorRestart" hidden>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                {{ __('advisor_restart') }}
            </button>
        </header>

        <section class="advisor-chat" id="advisorChat"
                 data-url="{{ route('front.advisor.recommend') }}" data-locale="{{ app()->getLocale() }}"
                 data-token="{{ csrf_token() }}" data-bot="{{ $bot }}" data-error="{{ __('advisor_error') }}">
            <script type="application/json" id="advisorStart">@json($start)</script>
            <div class="advisor-chat__log" id="advisorLog" aria-live="polite"></div>
            <div class="advisor-chat__typing" id="advisorTyping" hidden>
                <img src="{{ $bot }}" alt="" width="32" height="32">
                <span class="advisor-thinking__dots" aria-label="{{ __('advisor_typing') }}"><i></i><i></i><i></i></span>
            </div>
            <div class="advisor-chat__replies" id="advisorReplies"></div>
            <form class="advisor-chat__form" id="advisorForm" autocomplete="off">
                <input type="text" class="advisor-input" id="advisorInput" maxlength="300" placeholder="{{ __('advisor_input_placeholder') }}" aria-label="{{ __('advisor_input_placeholder') }}">
                <button type="submit" class="advisor-send" aria-label="{{ __('advisor_send') }}">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
                </button>
            </form>
        </section>

        <section class="advisor-results" id="advisorResults" hidden aria-live="polite" data-dynamic-cards>
            <h2 class="advisor-results__title">{{ __('advisor_results') }}</h2>
            <p class="advisor-results__intro" id="advisorIntro"></p>
            <div class="advisor-results__body" id="advisorBody"></div>
            <p class="advisor-results__note">{{ __('advisor_disclaimer') }}</p>
        </section>
    </main>
@endsection

@section('page-scripts')
    <script defer src="{{ asset_v('frontend/js/advisor.js') }}"></script>
@endsection
