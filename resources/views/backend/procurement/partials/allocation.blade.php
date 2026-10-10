{{-- Bir anbar seçimi (təminat hissəsi): mərhələ xətti, SMS vəziyyəti, növbəti addım. order-content.blade.php-dən çağırılır. --}}
@php
    $status = $allocation->status;
    $isCancelled = $status === \App\Models\Procurement\OrderItemAllocation::CANCELLED;
    $flow = \App\Models\Procurement\OrderItemAllocation::FLOW;
    $reached = array_search($status === 'problem' ? ($allocation->logs->where('to_status', 'problem')->last()?->from_status ?? 'selected') : $status, $flow, true);
    $lastLog = $allocation->logs->last();
    $statusUrl = route('admin.procurement.allocations.status', [$order, $allocation]);
    $allocTitle = $itemName($item).' — '.$allocation->warehouse->name_az.', '.$allocation->quantity.' ədəd';
    // Növbəti addım: bir düymə (əsas), qalanları menyuda
    $next = ['selected' => ['notified', 'Anbara bildirildi'], 'notified' => ['reserved', 'Anbar ayırdı'], 'reserved' => ['picked', 'Götürüldü']][$status] ?? null;
    // Telefonu olan anbara "bildirmək" = SMS (status özü "Anbara bildirildi" olur)
    $hasPhone = (bool) $allocation->warehouse->phone;
    $smsUrl = route('admin.procurement.allocations.sms', [$order, $allocation]);
    $allocSms = $smsLogs['allocation'][$allocation->id] ?? null;
    // Son SMS telefon nömrəsinə görə alınmayıb ("Telefon nömrəsi yanlışdır", "Nömrə formatı yanlışdır")
    $badPhone = $allocSms && !$allocSms->isSent() && \Illuminate\Support\Str::contains(mb_strtolower((string) $allocSms->error), 'nömrə');
@endphp
<div class="proc-alloc proc-alloc--{{ $status }}">
    <div class="proc-alloc__main">
        <strong>{{ $allocation->warehouse->name_az }}</strong>
        <span>{{ $allocation->quantity }} ədəd × {{ number_format((float) $allocation->unit_cost, 2) }} AZN</span>
        @if($allocation->warehouse->phone)<a href="tel:{{ $allocation->warehouse->phone }}" class="text-muted">{{ $allocation->warehouse->phone }}</a>@endif
    </div>
    @unless($isCancelled)
        <ol class="proc-steps" aria-label="Təminat mərhələləri">
            @foreach($flow as $i => $step)
                <li class="{{ $reached !== false && $i <= $reached ? 'is-done' : '' }}">{{ \App\Models\Procurement\OrderItemAllocation::LABELS[$step] }}</li>
            @endforeach
        </ol>
    @endunless
    @if($status === 'problem')
        <div class="proc-alloc__problem">⚠ {{ $lastLog?->note ?? 'Problem' }}</div>
    @endif
    @if($allocSms && !$isCancelled)
        <div class="proc-alloc__sms {{ $allocSms->isSent() ? 'is-ok' : 'is-bad' }}">{{ $allocSms->isSent() ? '✓ SMS '.$allocSms->created_at->format('d.m H:i') : '✕ SMS getmədi: '.$allocSms->error }}</div>
    @endif
    <div class="proc-alloc__foot">
        <span class="badge {{ ['cancelled' => 'bg-outline-muted', 'problem' => 'bg-danger', 'picked' => 'bg-success', 'reserved' => 'bg-outline-success', 'returning' => 'bg-warning', 'returned' => 'bg-outline-muted'][$status] ?? 'bg-outline-primary' }}">{{ $allocation->label() }}</span>
        @if($lastLog)<span class="text-muted small">{{ $lastLog->created_at->format('d.m H:i') }}@if($who($lastLog->user_id)) · {{ $who($lastLog->user_id) }}@endif @if($isCancelled && $lastLog->note) · {{ $lastLog->note }}@endif</span>@endif

        @if($flowEditable && !$isCancelled && $status !== 'picked')
            <div class="proc-alloc__actions">
                @if($status === 'selected' && $hasPhone && $badPhone)
                    {{-- SMS nömrəyə görə getməyib: təkrar göndərməyin mənası yoxdur — əvvəl nömrə düzəldilir (təkrar göndər "Digər"dədir) --}}
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.procurement.warehouses.edit', $allocation->warehouse) }}">Nömrəni düzəlt</a>
                @elseif($status === 'selected' && $hasPhone)
                    <form method="POST" action="{{ $smsUrl }}" data-once>@csrf<button class="btn btn-sm btn-primary">{{ $allocSms ? 'SMS-i təkrar göndər' : 'Anbara SMS göndər' }}</button></form>
                @elseif($next)
                    <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="{{ $next[0] }}"><button class="btn btn-sm btn-primary">{{ $next[1] }}</button></form>
                @endif
                @if($status === 'problem')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#procResumeModal"
                            data-action="{{ $statusUrl }}" data-title="{{ $allocTitle }}" data-price="{{ $allocation->problem_type === 'price_changed' ? number_format((float) $allocation->unit_cost, 2, '.', '') : '' }}">Həll edildi, davam et</button>
                @endif
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Digər</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if($status === 'selected' && $hasPhone && $badPhone)
                            <form method="POST" action="{{ $smsUrl }}" data-once>@csrf<button class="dropdown-item">SMS-i təkrar göndər</button></form>
                        @endif
                        @if($status === 'notified' && $hasPhone)
                            <form method="POST" action="{{ $smsUrl }}" data-once>@csrf<button class="dropdown-item">SMS-i təkrar göndər</button></form>
                        @endif
                        @if($status === 'selected' && $hasPhone)
                            <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="notified"><button class="dropdown-item">Bildirildi (SMS-siz, telefonla)</button></form>
                        @endif
                        @foreach(['reserved' => 'Anbar ayırdı (telefonla)', 'picked' => 'Götürüldü'] as $to => $label)
                            @if($status !== 'problem' && array_search($to, $flow, true) > array_search($status, $flow, true) && ($next[0] ?? null) !== $to)
                                <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="{{ $to }}"><button class="dropdown-item">{{ $label }}</button></form>
                            @endif
                        @endforeach
                        @if($status !== 'problem')
                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#procProblemModal" data-action="{{ $statusUrl }}" data-title="{{ $allocTitle }}">Problem bildir</button>
                        @endif
                        @if($editable)
                        <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#procCancelModal"
                                data-action="{{ route('admin.procurement.allocations.cancel', [$order, $allocation]) }}" data-title="{{ $allocTitle }}">Seçimi ləğv et</button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
