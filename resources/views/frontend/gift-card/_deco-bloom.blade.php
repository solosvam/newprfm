{{-- Bloom — Zərif: krem fon, adaçayı yaşılı. İkonun altında aypara formalı çiçəkli budaq (iri çiçəklər, qönçələr, yarpaqlar),
     yuxarıda iki kiçik budaqcıq; ikon Signature / Love ilə eyni nöqtədə (672, 236), koordinat 856×540. --}}
@php
    $cx = 672; $cy = 236; $r = 150;
    $pt = fn (float $deg, ?float $rad = null) => [$cx + ($rad ?? $r) * cos(deg2rad($deg)), $cy + ($rad ?? $r) * sin(deg2rad($deg))];
    // Aypara: 170° → 10° (aşağı yarım dairə), yarpaqlar hər iki tərəfə növbə ilə
    $leaves = [];
    $n = 18;
    for ($i = 0; $i <= $n; $i++) {
        $a = 170 - 160 * $i / $n;
        [$x, $y] = $pt($a);
        $edge = 1 - abs($i - $n / 2) / ($n / 2);           // ortada iri, uclarda kiçik
        $leaves[] = [$x, $y, $a - 90 + ($i % 2 ? 42 : -42) + ($i % 2 ? 0 : 180), .8 + .55 * $edge];
    }
    $flowers = [[$pt(90, 152), 1.25], [$pt(128), 1], [$pt(52), 1], [$pt(158, 146), .7], [$pt(22, 146), .7]];
    $buds = [[$pt(172), 200], [$pt(8), -20]];
    $sprigs = [[$pt(-55, 160), -40], [$pt(-125, 160), 220]];
@endphp
<svg class="gc-card__deco" viewBox="0 0 856 540" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="gcSage" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#4f6b4a"/><stop offset=".45" stop-color="#8fae86"/>
            <stop offset=".7" stop-color="#5d7c57"/><stop offset="1" stop-color="#7f9f78"/>
        </linearGradient>
    </defs>

    {{-- Budaq --}}
    <path d="{{ sprintf('M%.1f,%.1f A%d,%d 0 0 0 %.1f,%.1f', ...[...$pt(172), $r, $r, ...$pt(8)]) }}" fill="none" stroke="#8aa585" stroke-width="1.4" stroke-linecap="round" opacity=".7"/>
    {{-- Yarpaqlar --}}
    <g fill="#b9cfb2" stroke="#8aa585" stroke-width=".8" opacity=".85">
        @foreach($leaves as [$x, $y, $rot, $s])
            <path transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) rotate({{ round($rot, 1) }}) scale({{ round($s, 2) }})" d="M0,0 C8,-9 24,-9 32,0 C24,9 8,9 0,0Z"/>
        @endforeach
    </g>
    {{-- Yuxarıda iki budaqcıq --}}
    @foreach($sprigs as [[$x, $y], $rot])
        <g transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) rotate({{ $rot }}) scale(1.5)" opacity=".75">
            <path d="M0,0 C10,-4 22,-4 34,0" fill="none" stroke="#8aa585" stroke-width="1.2" stroke-linecap="round"/>
            <path d="M12,-2 C14,-12 22,-16 28,-14 C26,-8 20,-3 12,-2Z" fill="#c7d9c1" stroke="#8aa585" stroke-width=".7"/>
            <path d="M20,0 C22,9 30,13 36,11 C34,5 28,1 20,0Z" fill="#c7d9c1" stroke="#8aa585" stroke-width=".7"/>
        </g>
    @endforeach
    {{-- Qönçələr --}}
    @foreach($buds as [[$x, $y], $rot])
        <g transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) rotate({{ $rot }})">
            <path d="M0,0 C-7,-7 -7,-18 0,-26 C7,-18 7,-7 0,0Z" fill="#fbf1e6" stroke="#9bb497" stroke-width="1"/>
            <path d="M0,0 C-5,-4 -9,-4 -11,-1 M0,0 C5,-4 9,-4 11,-1" fill="none" stroke="#8aa585" stroke-width="1"/>
        </g>
    @endforeach
    {{-- İri çiçəklər --}}
    @foreach($flowers as [[$x, $y], $s])
        <g transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) scale({{ $s }})">
            @for($k = 0; $k < 5; $k++)
                <ellipse cx="0" cy="-11" rx="8" ry="11.5" transform="rotate({{ $k * 72 + 18 }})" fill="#fffaf1" stroke="#9bb497" stroke-width="1"/>
            @endfor
            <circle r="5" fill="#e3c27a"/>
            <circle r="5" fill="none" stroke="#c9a65a" stroke-width=".8"/>
        </g>
    @endforeach

    {{-- Mərkəzdə ikon (480×760 → 250 hündürlük), adaçayı gradienti --}}
    <g data-gc-icon transform="translate({{ $cx - 78.95 }} {{ $cy - 135 }}) scale(0.32895)" fill="url(#gcSage)">
        @include('frontend.gift-card._icon')
    </g>

    <rect x="22" y="22" width="812" height="496" rx="16" fill="none" stroke="#8aa585" stroke-width="1.2" opacity=".55"/>
</svg>
