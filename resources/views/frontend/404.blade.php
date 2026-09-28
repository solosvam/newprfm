@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/error.css') }}">
@endsection

@section('title', '404 — Səhifə tapılmadı | parfumshop')

@section('content')
    <main>
        <div class="wrap">
            <div class="error-page">
                <p class="error-page__code">404</p>
                <h1 class="error-page__title">Səhifə tapılmadı</h1>
                <p class="error-page__text">
                    Axtardığınız səhifə silinmiş, adı dəyişdirilmiş və ya heç vaxt mövcud olmayıb.
                </p>

                <div class="error-page__actions">
                    <a href="{{ route('home') }}" class="btn btn-dark error-page__btn">Ana səhifəyə qayıt</a>
                    <a href="{{ route('brands') }}" class="btn btn-outline error-page__btn">Brendlərə bax</a>
                </div>

                <div class="error-page__icon">
                    <svg viewBox="0 0 24 24" width="72" height="72" fill="none" stroke="currentColor" stroke-width="1.4">
                        <path d="M9 3h6l1 4H8l1-4Z"/>
                        <path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/>
                        <path d="M9.5 14.5c0-1.5 1-2.5 2.5-2.5s2.5 1 2.5 2.5" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </main>
@endsection
