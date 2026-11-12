{{--
  Kuryerin xərcləri (CourierExpenses::of). $self — kuryerin özü baxır; yoxsa baş kuryer ($courier, $cancellable — ləğv edilə bilənlərin id-ləri).
  Qəbz şəkli — basanda tam ekranda (data-zoom). Ləğv edilmiş xərc üstündən xətlə, balansa qayıdıb.
--}}
@if($expenses->isEmpty())
    <div class="empty">{{ $empty ?? 'Xərc yoxdur.' }}</div>
@else
    <div class="card list">
        @foreach($expenses as $expense)
            @php
                $cancelled = $expense->reversedBy;
                $photo = $expense->photo ? ($self ? route('kuryer.expenses.photo', $expense) : route('kuryer.team.expense-photo', [$courier, $expense])) : null;
            @endphp
            <div class="list__row expense {{ $cancelled ? 'is-cancelled' : '' }}">
                @if($photo)
                    <button type="button" class="thumb" data-zoom="{{ $photo }}" aria-label="Qəbzin şəkli"><img src="{{ $photo }}" alt="" loading="lazy"></button>
                @endif
                <div class="list__main">
                    <div class="list__what">{{ $expense->kind === 'outgo' ? collect([$expense->category?->name, $expense->note])->filter()->implode(' · ') : $expense->note }}</div>
                    <div class="list__meta">
                        {{ $expense->occurred_at->format('d.m.Y H:i') }}
                        @if($cancelled) · <span class="danger">ləğv edilib{{ $cancelled->user ? ' — '.trim($cancelled->user->full_name) : '' }}</span>@endif
                    </div>
                </div>
                <div class="list__sum">
                    <div class="{{ $cancelled ? 'muted' : 'ok' }}">−{{ number_format((float) $expense->amount, 2) }} AZN</div>
                    @if(!$self && in_array($expense->id, $cancellable ?? [], true))
                        <form method="POST" action="{{ route('kuryer.team.expense-cancel', [$courier, $expense]) }}" data-once
                              data-confirm="{{ number_format((float) $expense->amount, 2) }} AZN xərc ləğv edilsin? Məbləğ kuryerin balansına qayıdacaq.">
                            @csrf
                            <button class="btn btn--danger-outline btn--sm">Ləğv et</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
