@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar')

                <div class="account-cards">
                    <div class="account-card account-card-bonus">
                        <h2>{{ __('profile_your_bonus_balance') }}</h2>
                        <p class="account-card-amount">0,00 AZN</p>
                        <a href="#" class="account-card-link">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            <span>{{ __('profile_view_your_bonus_history') }}</span>
                        </a>
                    </div>

                    <div class="account-card">
                        <h2>{{ __('profile_your_latest_order') }}</h2>
                        <p class="account-card-empty">{{ __('profile_you_have_no_orders_yet') }}</p>
                    </div>

                    <div class="account-card">
                        <h2>{{ __('profile_where_is_my_order') }}</h2>
                        <p class="account-card-empty">{{ __('profile_no_active_orders') }}</p>
                    </div>

                    <div class="account-card account-card-birkart">
                        <div class="account-card-birkart-info">
                            <h3>
                                {{ __('profile_get_a_birkart') }}
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </h3>
                            <span>{{ __('profile_having_trouble_ordering_on_credit_apply_for_a_birkart') }}</span>
                        </div>
                        <img src="{{ asset('frontend/images/birbank.png') }}" alt="">
                    </div>

                    <div class="account-card account-card-invite">
                        <div class="account-card-invite-text">
                            <h3>{{ __('profile_invite_a_friend_and_earn_bonuses') }}</h3>
                            <button type="button" id="inviteButton" class="btn btn-dark account-card-invite-btn">{{ __('profile_send_invitation') }}</button>
                        </div>
                        <img src="{{ asset('frontend/images/invite.png') }}" alt="">
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
