{{-- Balans kartı: $balance (qəpik), $self — kuryerin özü ("verməlisiniz") və ya baş kuryer baxır ("verməlidir") --}}
@php $sum = number_format(abs($balance) / 100, 2).' AZN'; @endphp
<div class="balance {{ $balance > 0 ? 'balance--owe' : ($balance < 0 ? 'balance--due' : '') }}">
    <div class="balance__label">
        @if($balance > 0){{ $self ? 'Şirkətə təhvil verməlisiniz' : 'Şirkətə təhvil verməlidir' }}
        @elseif($balance < 0){{ $self ? 'Şirkətin sizə borcu' : 'Şirkətin ona borcu' }}
        @else Hesablaşma @endif
    </div>
    <div class="balance__value">{{ $sum }}</div>
</div>
