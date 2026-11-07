{{-- Kuryer → hesab: qalıq, xərclər ("Xərc yaz" — balansdan dərhal çıxır), haqq-hesab tarixçəsi, çıxış --}}
@extends('courier-app.layout', ['tab' => 'account'])
@section('title', 'Hesab')

@section('content')
    @include('courier-app._money', ['balance' => $balance, 'self' => true])

    <h2 class="section" id="expenses">Xərclər</h2>
    <details class="more" @if($errors->hasAny(['amount', 'note', 'photo'])) open @endif>
        <summary class="btn btn--primary btn--block">@include('courier-app._icon', ['name' => 'plus']) Xərc yaz</summary>
        {{-- Qəbz şəkli istəyə görədir: "Qəbzin şəkli" — tətbiqin kamerası, şəkil formaya qoşulur, forma özü göndərilmir (app.js data-photo-attach) --}}
        <form method="POST" action="{{ route('kuryer.expenses.store') }}" enctype="multipart/form-data" class="more__body" data-once>
            @csrf
            <label class="label" for="expenseAmount">Məbləğ (AZN)</label>
            <input type="number" step="0.01" min="0.01" max="10000" name="amount" id="expenseAmount" class="field" inputmode="decimal" value="{{ old('amount') }}" required>
            <label class="label" for="expenseNote">Açıqlama</label>
            <input type="text" name="note" id="expenseNote" class="field" maxlength="200" placeholder="Məsələn: Taxi" value="{{ old('note') }}" required>
            <input type="file" name="photo" accept="image/*" hidden data-photo-input>
            <button type="button" class="btn btn--outline btn--block" data-photo-attach="Qəbzin şəklini çəkin">@include('courier-app._icon', ['name' => 'camera']) Qəbzin şəkli (istəyə görə)</button>
            <div class="photo-done" data-photo-done hidden>
                <img alt="" data-photo-preview>
                <span>Şəkil əlavə olundu</span>
                <button type="button" class="btn btn--ghost btn--sm" data-photo-remove>Sil</button>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Yadda saxla</button>
            <p class="muted">Məbləğ balansınızdan dərhal çıxılır. Səhv yazsanız, baş kuryerə deyin — ləğv edəcək.</p>
        </form>
    </details>
    @include('courier-app._expenses', ['expenses' => $expenses, 'self' => true, 'empty' => 'Hələ xərc yazmamısınız.'])

    <h2 class="section">Haqq-hesab tarixçəsi</h2>
    @include('courier-app._history', ['history' => $history, 'self' => true])

    <a href="{{ route('admin.logout') }}" class="btn btn--ghost btn--block logout" data-turbo="false">@include('courier-app._icon', ['name' => 'logout']) Çıxış</a>
@endsection
