{{--
  Balans kartı: $balance (qəpik), $self — kuryerin özü ("verməlisiniz") və ya baş kuryer baxır ("verməlidir").
  Məbləğ bulanıq açılır; göz düyməsi ilə görünür (rəqəm 0-dan sayılaraq) və yenidən gizlənir — app.js [data-money].
--}}
@php $sum = number_format(abs($balance) / 100, 2).' AZN'; @endphp
<div class="balance {{ $balance > 0 ? 'balance--owe' : ($balance < 0 ? 'balance--due' : '') }}">
    <div class="balance__label">
        @if($balance > 0){{ $self ? 'Şirkətə təhvil verməlisiniz' : 'Şirkətə təhvil verməlidir' }}
        @elseif($balance < 0){{ $self ? 'Şirkətin sizə borcu' : 'Şirkətin ona borcu' }}
        @else Hesablaşma @endif
    </div>
    <div class="balance__row" data-money>
        <div class="balance__value is-hidden" data-money-value="{{ number_format(abs($balance) / 100, 2, '.', '') }}">{{ $sum }}</div>
        <button type="button" class="eye" data-money-toggle aria-pressed="false" aria-label="Məbləği göstər">
            <span class="eye__show">@include('courier-app._icon', ['name' => 'eye'])</span>
            <span class="eye__hide">@include('courier-app._icon', ['name' => 'eye-off'])</span>
        </button>
    </div>
</div>
