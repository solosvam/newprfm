{{-- Ayarlardan qurulan məlumat səhifəsi (məs. bonus proqramı): $title, $meta, $facts, $html (artıq escape olunub), $active --}}
@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/info.css') }}">
@endsection

@section('title', $title.' | Parfumshop.az')
@section('meta_description', $meta)
@section('og_title', $title.' | Parfumshop.az')

@section('content')
    <main class="info-page">
        <div class="info-layout">
            @include('frontend.partials.info-nav')

            <article class="info-card">
                <h1 class="info-card__title">{{ $title }}</h1>
                @if(!empty($facts))
                    <div class="info-facts info-facts--{{ count($facts) }}">
                        @foreach($facts as $fact)
                            <div class="info-fact">
                                <div class="info-fact__label">{{ $fact['label'] }}</div>
                                <div class="info-fact__value">{{ $fact['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="info-content">{!! $html !!}</div>
            </article>
        </div>
    </main>
@endsection
