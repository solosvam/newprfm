{{--
  Yeni əməliyyat (Clopos üslubu): Məxaric / Mədaxil / Köçürmə. Bölmələr və siyahılar seçilən növə görə finance.js-də dəyişir.
  Parametrlər: $accounts (aktiv hesablar), $balances, $categories (FinanceCategory::tree()).
--}}
@php
    $oldKind = old('kind', 'outgo');
    $own = $accounts->whereIn('type', \App\Models\Finance\FinanceAccount::OWN)
        ->sortBy(fn ($a) => array_search($a->type, \App\Models\Finance\FinanceAccount::OWN, true))->values();
    $persons = $accounts->where('type', 'person');
    $couriers = $accounts->where('type', 'courier');
    $amount = fn ($a) => ' — '.(($balances[$a->id] ?? 0) < 0 ? '−' : '').number_format(abs($balances[$a->id] ?? 0) / 100, 2).' AZN';
@endphp
<div class="modal modal-right fade" id="newMovement" tabindex="-1" aria-labelledby="newMovementTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="newMovementTitle">Yeni əməliyyat</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="btn-group w-100 mb-3" role="group" aria-label="Əməliyyat növü">
                @foreach(['outgo' => 'Məxaric', 'income' => 'Mədaxil', 'transfer' => 'Köçürmə'] as $kind => $label)
                    <input type="radio" class="btn-check" name="kind" id="mvKind-{{ $kind }}" value="{{ $kind }}" autocomplete="off" @checked($oldKind === $kind)>
                    <label class="btn btn-outline-primary" for="mvKind-{{ $kind }}">{{ $label }}</label>
                @endforeach
            </div>

            <div class="mb-3"><label class="form-label" for="mvAmount">Məbləğ, AZN</label>
                <input id="mvAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" value="{{ old('amount') }}" required></div>

            <div class="mb-3" data-for="outgo income"><label class="form-label" for="mvAccount">Balans</label>
                <select id="mvAccount" name="account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    <optgroup label="Hesablar">
                        @foreach($own as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->name }}{{ $amount($a) }}</option>@endforeach
                    </optgroup>
                    @if($persons->isNotEmpty())
                        {{-- Şəxs öz cibindən ödəyibsə — Məxaric şəxsdən (şirkət ona borclu qalır) --}}
                        <optgroup label="Şəxslər (öz pulundan ödəyib)">
                            @foreach($persons as $a)<option value="{{ $a->id }}" data-kinds="outgo" @selected((int) old('account_id') === $a->id)>{{ $a->name }}{{ $amount($a) }}</option>@endforeach
                        </optgroup>
                    @endif
                </select></div>

            <div class="mb-3" data-for="outgo income"><label class="form-label" for="mvCategory">Kateqoriya</label>
                <select id="mvCategory" name="category_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($categories as $c)
                        @continue(in_array($c->code, \App\Models\Finance\FinanceCategory::AUTO, true))
                        <option value="{{ $c->id }}" data-type="{{ $c->type }}" @selected((int) old('category_id') === $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>
                    @endforeach
                </select>
                <div class="form-text"><a href="{{ route('admin.finance.categories') }}">Kateqoriyalar</a></div></div>

            @foreach(['from' => 'Haradan', 'to' => 'Haraya'] as $side => $label)
                <div class="mb-3" data-for="transfer"><label class="form-label" for="mv-{{ $side }}">{{ $label }}</label>
                    <select id="mv-{{ $side }}" name="{{ $side }}_account_id" class="form-select" required>
                        <option value="">Seçin</option>
                        <optgroup label="Hesablar">
                            @foreach($own as $a)
                                @continue($side === 'to' && $a->type === 'online')
                                <option value="{{ $a->id }}" @selected((int) old($side.'_account_id') === $a->id)>{{ $a->name }}{{ $amount($a) }}</option>
                            @endforeach
                        </optgroup>
                        @if($persons->isNotEmpty())
                            <optgroup label="Şəxslər">
                                @foreach($persons as $a)<option value="{{ $a->id }}" @selected((int) old($side.'_account_id') === $a->id)>{{ $a->name }}{{ $amount($a) }}</option>@endforeach
                            </optgroup>
                        @endif
                        @if($couriers->isNotEmpty())
                            <optgroup label="Kuryerlər">
                                @foreach($couriers as $a)<option value="{{ $a->id }}" @selected((int) old($side.'_account_id') === $a->id)>{{ $a->name }}{{ $amount($a) }}</option>@endforeach
                            </optgroup>
                        @endif
                    </select></div>
            @endforeach

            <div class="mb-3"><label class="form-label" for="mvAt">Əməliyyat vaxtı <small class="text-muted">(boş — indi)</small></label>
                <input id="mvAt" type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at') }}"></div>
            <label class="form-label" for="mvNote">Açıqlama</label>
            <textarea id="mvNote" name="note" rows="2" maxlength="2000" class="form-control">{{ old('note') }}</textarea>
            <div class="form-text mt-2" data-for="transfer">Şəxsdən borc aldıq: Şəxs → Kassa. Borcu qaytardıq: Kassa → Şəxs. Kuryerə avans: Kassa → Kuryer; kuryerdən təhvil: Kuryer → Nağd kassa.</div>
            <div class="form-text mt-2" data-for="outgo income">Anbara ödəniş — anbarın kartından. Kuryerdən pulu təhvil almaq — kuryerin kartından və ya "Köçürmə".</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qeydə al</button></div>
    </form></div>
</div>
