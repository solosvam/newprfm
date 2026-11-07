{{--
  Kuryerin son yeri (kuryer tətbiqi açılanda göndərir): vaxt, dəqiqlik; $link — Google Maps linki
  (siyahıda kart özü linkdir — orada link olmur). $user
--}}
@if($url = $user->locationMapsUrl())
    <span class="courier-location">
        📍 {{ $user->location_at?->isToday() ? 'bu gün '.$user->location_at->format('H:i') : $user->location_at?->format('d.m.Y H:i') }}
        @if($user->location_accuracy) · ±{{ $user->location_accuracy }} m @endif
        @if($link ?? false) · <a href="{{ $url }}" target="_blank" rel="noopener">Xəritədə aç</a> @endif
    </span>
@else
    <span class="text-muted">Yer məlum deyil</span>
@endif
