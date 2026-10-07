{{-- Hissə-hissə ödəniş: müddətlər (Ayarlar → Kredit → Faizlər) və qaydalar (→ Şərtlər və qaydalar) --}}
@extends('frontend.layouts.app')

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $active = 'installment';
@endphp

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/info.css') }}">
@endsection

@section('title', __('page_installment_title').' | Parfumshop.az')
@section('meta_description', __('page_installment_meta'))
@section('og_title', __('page_installment_title').' | Parfumshop.az')

@section('content')
    <main class="info-page">
        <div class="info-layout">
            @include('frontend.partials.info-nav')

            <article class="info-card">
                <h1 class="info-card__title">{{ __('page_installment_title') }}</h1>

                @if($periods->isNotEmpty())
                    <h2 class="info-subtitle">{{ __('installment_periods') }}</h2>
                    <div class="info-table-wrap">
                        <table class="info-table">
                            <thead>
                            <tr>
                                <th>{{ __('installment_col_period') }}</th>
                                <th>{{ __('installment_col_rate') }}</th>
                                <th>{{ __('installment_col_amount') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($periods as $period)
                                <tr>
                                    <td class="info-table__strong">{{ __('installment_months', ['months' => $period->month]) }}</td>
                                    <td>
                                        @if((float) $period->interest_rate <= 0)
                                            <span class="info-badge">{{ __('installment_free') }}</span>
                                        @else
                                            {{ $num($period->interest_rate) }}%
                                        @endif
                                    </td>
                                    <td>{{ $period->min_amount !== null ? __('installment_from', ['amount' => $num($period->min_amount)]) : __('installment_any') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($rules->isNotEmpty())
                    <h2 class="info-subtitle">{{ __('installment_rules') }}</h2>
                    <ol class="info-rules">
                        @foreach($rules as $rule)
                            <li>{!! nl2br(e($rule)) !!}</li>
                        @endforeach
                    </ol>
                @endif
            </article>
        </div>
    </main>
@endsection
