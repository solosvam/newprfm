{{-- Celebration — Özəl gün: şampan fon, qızılı. İkonun arxasından nazik şüalar (salyut), ətrafında parıltılar, konfetti və
     iki qıvrım lent; ikon Signature / Love / Bloom ilə eyni nöqtədə (672, 236), koordinat 856×540. --}}
@php
    $cx = 672; $cy = 236;
    $rays = [];
    for ($a = 0; $a < 360; $a += 7.5) {
        $long = ((int) ($a / 7.5)) % 2 === 0;
        $r1 = 112; $r2 = $long ? 178 : 150;
        $rays[] = [$cx + $r1 * cos(deg2rad($a)), $cy + $r1 * sin(deg2rad($a)), $cx + $r2 * cos(deg2rad($a)), $cy + $r2 * sin(deg2rad($a))];
    }
    // Parıltılar (4 uclu ulduz): x, y, ölçü
    $sparkles = [[520, 96, 9], [826, 168, 7], [838, 392, 8], [548, 392, 6], [590, 60, 5], [760, 66, 6], [474, 250, 5], [834, 268, 4]];
    // Konfetti: x, y, bucaq, rəng
    $confetti = [[548, 150, 30, '#b8893b'], [800, 110, -40, '#d9b56a'], [500, 330, 60, '#c99a45'], [770, 380, 10, '#b8893b'],
                 [604, 450, -25, '#d9b56a'], [836, 214, 45, '#c99a45'], [470, 170, -15, '#d9b56a'], [560, 474, 70, '#c99a45']];
@endphp
<svg class="gc-card__deco" viewBox="0 0 856 540" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="gcChamp" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#7a5a22"/><stop offset=".42" stop-color="#c79a45"/>
            <stop offset=".68" stop-color="#6b4c1a"/><stop offset="1" stop-color="#a77d33"/>
        </linearGradient>
    </defs>

    {{-- Şüalar --}}
    <g stroke="#b8893b" stroke-width="1.1" stroke-linecap="round" opacity=".32">
        @foreach($rays as [$x1, $y1, $x2, $y2])
            <line x1="{{ round($x1, 1) }}" y1="{{ round($y1, 1) }}" x2="{{ round($x2, 1) }}" y2="{{ round($y2, 1) }}"/>
        @endforeach
    </g>
    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="104" fill="none" stroke="#b8893b" stroke-width="1" opacity=".35"/>

    {{-- Qıvrım lentlər --}}
    <g fill="none" stroke="#c99a45" stroke-width="1.6" stroke-linecap="round" opacity=".55">
        <path d="M478,74 C506,90 492,118 520,128 C548,138 540,100 566,112"/>
        <path d="M772,312 C796,294 822,314 808,336 C796,356 768,340 786,320 C802,302 832,324 830,356"/>
    </g>

    {{-- Konfetti --}}
    @foreach($confetti as [$x, $y, $rot, $color])
        <rect x="{{ $x }}" y="{{ $y }}" width="10" height="4" rx="1.5" transform="rotate({{ $rot }} {{ $x }} {{ $y }})" fill="{{ $color }}" opacity=".75"/>
    @endforeach
    <g fill="#c99a45" opacity=".6">
        <circle cx="584" cy="118" r="3"/><circle cx="786" cy="148" r="2.5"/><circle cx="530" cy="430" r="2.5"/>
        <circle cx="822" cy="320" r="3"/><circle cx="640" cy="486" r="2"/><circle cx="486" cy="210" r="2"/>
    </g>

    {{-- Parıltılar --}}
    @foreach($sparkles as [$x, $y, $s])
        <path transform="translate({{ $x }} {{ $y }}) scale({{ $s / 10 }})" d="M0,-10 Q0,0 10,0 Q0,0 0,10 Q0,0 -10,0 Q0,0 0,-10Z" fill="url(#gcChamp)" opacity=".85"/>
    @endforeach

    {{-- Mərkəzdə ikon (480×760 → 200 hündürlük) --}}
    <g data-gc-icon transform="translate({{ $cx - 63.16 }} {{ $cy - 100 }}) scale(0.26316)" fill="url(#gcChamp)">
        @include('frontend.gift-card._icon')
    </g>

    <rect x="22" y="22" width="812" height="496" rx="16" fill="none" stroke="#b8893b" stroke-width="1.2" opacity=".55"/>
</svg>
