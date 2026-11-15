@extends('frontend.layouts.app')

@section('title', __('gift_card_title').' | Parfumshop.az')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/gift-card.css') }}">
@endsection

@section('content')
    @php
        $quick = [100, 200, 300, 500];
        $default = old('amount', 200);
    @endphp
    <main class="gc-page">
        <div class="gc-head">
            <h1>{{ __('gift_card_title') }}</h1>
            <p>{{ __('gift_card_subtitle') }}</p>
        </div>

        <div class="gc-layout">
            <section class="gc-preview" aria-label="{{ __('gift_card_preview') }}">
                @php $selectedDesign = old('design', $defaultDesign); @endphp
                {{-- Göndəriləcək açıqcanın özü (canlı): kartın səhifəsindəki "Endir" ilə eyni çəkiliş (pages/gift-card.js) --}}
                <canvas class="gc-postcard" width="1080" height="1350" data-gc-postcard data-how="{{ __('gift_card_how_to_use') }}"
                        role="img" aria-label="{{ __('gift_card_preview') }}"></canvas>
                <p class="gc-hint gc-preview__hint">{{ __('gift_card_preview_hint') }}</p>
                {{-- Açıqca bu kartlardan çəkilir (dizayn, məbləğ); özləri görünmür --}}
                <div hidden>
                    @foreach($designs as $d)
                        <div data-gc-design-preview="{{ $d }}">
                            @include('frontend.gift-card._card', ['amount' => $default, 'code' => null, 'expires' => null, 'design' => $d])
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="gc-form-wrap">
                @if(session('error'))
                    <div class="form-alert form-alert-error" role="alert">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="form-alert form-alert-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('gift-card.store') }}" class="gc-form" data-gc-form
                      data-min="{{ $min }}" data-max="{{ $max }}" data-step="{{ $step }}">
                    @csrf
                    <label class="gc-label">{{ __('gift_card_design_label') }}</label>
                    <div class="gc-designs" role="radiogroup">
                        @foreach($designs as $d)
                            <label class="gc-design" title="{{ __('gift_card_design_'.$d) }}">
                                <input type="radio" name="design" value="{{ $d }}" data-gc-design @checked($d === $selectedDesign)>
                                <span class="gc-design__swatch gc-design__swatch--{{ $d }}">{{ __('gift_card_design_'.$d) }}</span>
                            </label>
                        @endforeach
                    </div>

                    <label class="gc-label">{{ __('gift_card_amount_label') }}</label>
                    <div class="gc-quick" role="group">
                        @foreach($quick as $q)
                            <button type="button" class="gc-quick__btn" data-gc-quick="{{ $q }}">{{ $q }} ₼</button>
                        @endforeach
                    </div>
                    <div class="gc-stepper">
                        <button type="button" class="gc-stepper__btn" data-gc-step="-1" aria-label="{{ __('gift_card_amount_less') }}">−</button>
                        <output class="gc-stepper__value"><span data-gc-value>{{ (int) $default }}</span> ₼</output>
                        <button type="button" class="gc-stepper__btn" data-gc-step="1" aria-label="{{ __('gift_card_amount_more') }}">+</button>
                    </div>
                    <input type="hidden" name="amount" value="{{ (int) $default }}" data-gc-amount-input>
                    <p class="gc-hint">{{ __('gift_card_amount_hint', ['min' => $min, 'max' => $max, 'step' => $step]) }}</p>

                    <label class="gc-label" for="gcMessage">{{ __('gift_card_message_label') }}</label>
                    <textarea id="gcMessage" name="message" rows="3" maxlength="{{ $messageMax }}" class="gc-input"
                              placeholder="{{ __('gift_card_message_placeholder') }}" data-gc-message>{{ old('message') }}</textarea>
                    <p class="gc-hint gc-hint--right"><span data-gc-count>0</span> / {{ $messageMax }}</p>

                    <label class="gc-label" for="gcSender">{{ __('gift_card_sender_label') }}</label>
                    <input id="gcSender" type="text" name="sender_name" maxlength="{{ $senderMax }}" class="gc-input"
                           placeholder="{{ __('gift_card_sender_placeholder') }}" value="{{ old('sender_name') }}" data-gc-sender>

                    @auth
                        <label class="gc-terms">
                            <input type="checkbox" name="accept_terms" value="1" required @checked(old('accept_terms'))>
                            <span>{{ __('gift_card_accept_terms', ['months' => $validMonths]) }}</span>
                        </label>
                        <button type="submit" class="btn btn-dark gc-submit">{{ __('gift_card_pay') }} · <span data-gc-pay>{{ (int) $default }}</span> ₼</button>
                    @else
                        <a class="btn btn-dark gc-submit" href="{{ route('front.login', ['redirect' => route('gift-card.index')]) }}">{{ __('gift_card_login_to_buy') }}</a>
                    @endauth
                    <p class="gc-hint">{{ __('gift_card_online_only') }}</p>
                </form>

                @if($myCards->isNotEmpty())
                    <h2 class="gc-my__title">{{ __('gift_card_my_cards') }}</h2>
                    <ul class="gc-my">
                        @foreach($myCards as $c)
                            <li>
                                <a href="{{ route('gift-card.show', $c->share_token) }}">
                                    <strong>{{ (int) $c->amount }} ₼</strong>
                                    <span>{{ $c->formattedCode() }}</span>
                                    <em class="gc-status gc-status--{{ $c->isExpired() && $c->status === 'active' ? 'expired' : $c->status }}">{{ __('gift_card_status_'.($c->isExpired() && $c->status === 'active' ? 'expired' : $c->status)) }}</em>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Necə işləyir? --}}
        <section class="gc-how" aria-labelledby="gcHowTitle">
            <h2 id="gcHowTitle">{{ __('gift_card_how_title') }}</h2>
            <ol class="gc-how__list">
                <li><span>1</span><p>{{ __('gift_card_rule_code') }}</p></li>
                <li><span>2</span><p>{{ __('gift_card_rule_valid', ['months' => $validMonths]) }}</p></li>
                <li><span>3</span><p>{{ __('gift_card_rule_use') }}</p></li>
                <li><span>4</span><p>{{ __('gift_card_rule_burn') }}</p></li>
            </ol>
        </section>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/gift-card.js') }}" defer></script>
@endsection
