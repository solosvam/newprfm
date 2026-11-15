@extends('frontend.layouts.app')

@section('title', __('gift_card_title').' | Parfumshop.az')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/gift-card.css') }}">
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    @php
        $paid = $card->status !== \App\Models\GiftCard\GiftCard::PENDING;
        $statusKey = $card->status === 'active' && $card->isExpired() ? 'expired' : $card->status;
    @endphp
    <main class="gc-page gc-page--show">
        @if(session('success'))
            <div class="form-alert form-alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="form-alert form-alert-error" role="alert">{{ session('error') }}</div>
        @endif

        <div class="gc-show" data-gc-show
             data-share-url="{{ $shareUrl }}"
             data-share-title="{{ __('gift_card_share_text') }}"
             data-file-name="parfumshop-hediyye-karti-{{ (int) $card->amount }}.png"
             data-how="{{ __('gift_card_how_to_use') }}"
             data-from-label="{{ __('gift_card_from') }}"
             data-message="{{ $card->message }}"
             data-sender="{{ $card->sender_name }}"
             data-copied="{{ __('gift_card_copied') }}">

            @include('frontend.gift-card._card', [
                'amount' => $card->amount,
                'code' => $paid ? $card->formattedCode() : null,
                'expires' => $card->expires_at?->format('d.m.Y'),
                'design' => $card->design,
            ])

            @if($card->message || $card->sender_name)
                <div class="gc-show__note">
                    @if($card->message)<p class="gc-show__msg">“{{ $card->message }}”</p>@endif
                    @if($card->sender_name)<p class="gc-show__from">— {{ $card->sender_name }}</p>@endif
                </div>
            @endif

            @if(!$paid)
                <p class="gc-show__state">{{ __('gift_card_awaiting_payment') }}</p>
                @if($isOwner)
                    <form method="POST" action="{{ route('gift-card.pay', $card->share_token) }}">
                        @csrf
                        <button type="submit" class="btn btn-dark">{{ __('gift_card_pay_again') }}</button>
                    </form>
                @endif
            @else
                @if($statusKey !== 'active')
                    <p class="gc-show__state gc-status gc-status--{{ $statusKey }}">{{ __('gift_card_status_'.$statusKey) }} · {{ __('gift_card_not_available') }}</p>
                @else
                    <p class="gc-show__how">{{ __('gift_card_how_to_use') }}</p>
                @endif
                <div class="gc-actions">
                    <button type="button" class="btn btn-dark" data-gc-share>{{ __('gift_card_share') }}</button>
                    <button type="button" class="btn btn-outline" data-gc-download>{{ __('gift_card_download') }}</button>
                    <button type="button" class="gc-copy" data-gc-copy="{{ $card->formattedCode() }}">{{ __('gift_card_copy_code') }}</button>
                </div>
            @endif

            <a class="gc-show__more" href="{{ route('gift-card.index') }}">{{ __('gift_card_buy_another') }}</a>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/gift-card.js') }}" defer></script>
@endsection
