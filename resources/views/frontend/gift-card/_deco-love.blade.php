{{-- Love — Romantik: bordo fon, pudra çəhrayısı. İki budaqdan dairəvi çələng (yarpaqlar, kiçik çiçəklər, qönçələr),
     mərkəzində Parfumshop ikonu — Signature-dakı kimi eyni nöqtədə (672, 236), koordinat 856×540. --}}
@php
    $cx = 672; $cy = 236; $r = 142;
    $pt = fn (float $deg, float $rad = null) => [$cx + ($rad ?? $r) * cos(deg2rad($deg)), $cy + ($rad ?? $r) * sin(deg2rad($deg))];
    // Çələng: sol budaq 100°→250°, sağ budaq 80°→-70° (aşağıda birləşir, yuxarıda açıqdır)
    $leaves = [];
    foreach ([[100, 262, 1], [80, -82, -1]] as [$from, $to, $dir]) {
        $n = 15;
        for ($i = 0; $i <= $n; $i++) {
            $a = $from + ($to - $from) * $i / $n;
            [$x, $y] = $pt($a);
            $tangent = $a + 90 * $dir;
            $size = 1 - 0.45 * $i / $n;                 // budağın ucuna doğru kiçilir
            $leaves[] = [$x, $y, $tangent + ($i % 2 ? 38 : -38), $size];
        }
    }
    $blossoms = [[$pt(150), 1], [$pt(205), .8], [$pt(35), 1], [$pt(-25), .8], [$pt(90, 150), 1.15]];
    $buds = [[$pt(258), 250], [$pt(-78), -70]];
    $arc = fn (float $a1, float $a2) => sprintf('M%.1f,%.1f A%d,%d 0 0 %d %.1f,%.1f', ...[...$pt($a1), $r, $r, $a2 > $a1 ? 1 : 0, ...$pt($a2)]);
@endphp
<svg class="gc-card__deco" viewBox="0 0 856 540" preserveAspectRatio="none" aria-hidden="true">
    <defs>
        <linearGradient id="gcPink" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#e7aeb5"/><stop offset=".45" stop-color="#fbe7e7"/>
            <stop offset=".7" stop-color="#dba0a8"/><stop offset="1" stop-color="#f4d2d5"/>
        </linearGradient>
    </defs>

    {{-- Budaqlar --}}
    <g fill="none" stroke="#f2c6cb" stroke-width="1.3" stroke-linecap="round" opacity=".55">
        <path d="{{ $arc(100, 262) }}"/>
        <path d="{{ $arc(80, -82) }}"/>
    </g>
    {{-- Yarpaqlar --}}
    <g fill="url(#gcPink)" opacity=".5">
        @foreach($leaves as [$x, $y, $rot, $s])
            <path transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) rotate({{ round($rot, 1) }}) scale({{ round($s, 2) }})" d="M0,0 C7,-7 19,-7 26,0 C19,7 7,7 0,0Z"/>
        @endforeach
    </g>
    {{-- Kiçik çiçəklər (5 ləçək) --}}
    @foreach($blossoms as [[$x, $y], $s])
        <g transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) scale({{ $s }})" opacity=".8">
            @for($k = 0; $k < 5; $k++)
                <ellipse cx="0" cy="-7" rx="5" ry="7.5" transform="rotate({{ $k * 72 }})" fill="url(#gcPink)"/>
            @endfor
            <circle r="3.2" fill="#a8495c" opacity=".7"/>
        </g>
    @endforeach
    {{-- Qönçələr budaqların ucunda --}}
    @foreach($buds as [[$x, $y], $rot])
        <path transform="translate({{ round($x, 1) }} {{ round($y, 1) }}) rotate({{ $rot }})" d="M0,0 C-6,-6 -6,-16 0,-22 C6,-16 6,-6 0,0Z" fill="url(#gcPink)" opacity=".75"/>
    @endforeach

    {{-- Mərkəzdə ikon (480×760 → 250 hündürlük) --}}
    <g data-gc-icon transform="translate({{ $cx - 78.95 }} {{ $cy - 125 }}) scale(0.32895)" fill="url(#gcPink)">
        @include('frontend.gift-card._icon')
    </g>

    <rect x="22" y="22" width="812" height="496" rx="16" fill="none" stroke="#f2c6cb" stroke-width="1.2" opacity=".5"/>
</svg>
