{{--
  Kuryerin son yeri (kuryer tətbiqi göndərir): vaxt, dəqiqlik; $link — səhifədəki xəritəyə keçid
  (kuryerin səhifəsində; siyahıda kart özü linkdir). $user
--}}
@if($user->location_at)
    <span class="courier-location">
        📍 {{ $user->location_at?->isToday() ? 'bu gün '.$user->location_at->format('H:i') : $user->location_at?->format('d.m.Y H:i') }}
        @if($user->location_accuracy) · ±{{ $user->location_accuracy }} m @endif
        @if($link ?? false) · <a href="#locations">Xəritədə bax</a> @endif
    </span>
@else
    <span class="text-muted">Yer məlum deyil</span>
@endif
