{{--
  Hesab: yaratmaq (Nağd / Kart / Bank / Şəxs + başlanğıc qalıq) və ya ad / açıqlama dəyişmək, deaktiv etmək.
  Parametrlər: $editAccount (null — yeni hesab), $editBalance (qəpik, yalnız redaktədə).
--}}
@php $isNew = !$editAccount; @endphp
<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" data-account-form
            action="{{ $isNew ? route('admin.finance.accounts.store') : route('admin.finance.accounts.update', $editAccount) }}">
        @csrf
        @unless($isNew) @method('PUT') @endunless
        <div class="modal-header"><h5 class="modal-title" id="accountModalTitle">{{ $isNew ? 'Yeni hesab' : 'Hesabı redaktə et' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="accName">Ad</label>
                <input id="accName" name="name" maxlength="120" class="form-control" value="{{ old('name', $editAccount?->name) }}" required></div>
            @if($isNew)
                <div class="mb-3"><label class="form-label" for="accType">Növ</label>
                    <select id="accType" name="type" class="form-select" required>
                        @foreach(\App\Models\Finance\FinanceAccount::CREATABLE as $type)
                            <option value="{{ $type }}" @selected(old('type', 'cash') === $type)>{{ \App\Models\Finance\FinanceAccount::TYPES[$type] }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Şəxs — pul aldığımız və ya verdiyimiz adam (sahibkar, borc verən tanış). Onun qalığı şirkətin pulu sayılmır.</div></div>
                <div class="mb-3" data-account-opening><label class="form-label" for="accOpening">Başlanğıc qalıq, AZN</label>
                    <input id="accOpening" type="number" name="opening" min="0" step="0.01" class="form-control" value="{{ old('opening') }}" placeholder="0">
                    <div class="form-text">"Düzəliş" kateqoriyalı mədaxil kimi yazılır.</div></div>
            @endif
            <label class="form-label" for="accDescription">Açıqlama</label>
            <textarea id="accDescription" name="description" rows="2" maxlength="2000" class="form-control">{{ old('description', $editAccount?->description) }}</textarea>
            @if(!$isNew && !$editAccount->active)
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="activate" value="1" id="accActivate">
                    <label class="form-check-label" for="accActivate">Hesabı yenidən aktiv et</label>
                </div>
            @elseif(!$isNew && !$editAccount->code)
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="deactivate" value="1" id="accDeactivate" @disabled($editBalance !== 0)>
                    <label class="form-check-label" for="accDeactivate">Hesabı deaktiv et</label>
                    @if($editBalance !== 0)<div class="form-text">Qalıq sıfır olmalıdır — əvvəlcə pulu köçürün.</div>@endif
                </div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">{{ $isNew ? 'Yarat' : 'Yadda saxla' }}</button></div>
    </form></div>
</div>
