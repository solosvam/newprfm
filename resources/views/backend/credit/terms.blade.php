@php
    $html_tag_data = [];
    $title = 'Kredit şərtləri və qaydaları';
    $breadcrumbs = ["/"=>"ParfumShop", ""=>$title];
@endphp
@extends('backend.layout',['html_tag_data'=>$html_tag_data, 'title'=>$title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
        @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
    </div>
    <div class="card">
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif
            <p class="text-muted">Hər qaydanı ayrıca əlavə edin. Sıralamanı dəyişmək üçün yuxarı/aşağı düymələrindən istifadə edin.</p>
            <form method="POST" action="{{ route('admin.credit.terms.update') }}" id="creditTermsForm">
                @csrf
                <div id="creditTermItems">
                    @foreach(old('items', $items->toArray()) as $index => $item)
                        <div class="card mb-3 credit-term-item">
                            <div class="card-body">
                                <input type="hidden" data-field="id" name="items[{{ $index }}][id]" value="{{ $item['id'] ?? '' }}">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <strong class="credit-term-number">Qayda {{ $loop->iteration }}</strong>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-move="-1">↑</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-move="1">↓</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove>Sil</button>
                                    </div>
                                </div>
                                <div class="row">
                                    @foreach(['az' => 'AZ', 'en' => 'EN', 'ru' => 'RU'] as $locale => $label)
                                        <div class="col-md-4">
                                            <label class="form-label">Qayda {{ $label }}</label>
                                            <textarea class="form-control mb-3" rows="5" data-field="content_{{ $locale }}" name="items[{{ $index }}][content_{{ $locale }}]" @required($locale === 'az')>{{ $item['content_'.$locale] ?? '' }}</textarea>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-primary" id="addCreditTerm">+ Yeni qayda</button>
                <button class="btn btn-primary" type="submit">Yadda saxla</button>
            </form>
        </div>
    </div>
</div>
<template id="creditTermTemplate">
    <div class="card mb-3 credit-term-item">
        <div class="card-body">
            <input type="hidden" data-field="id" value="">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong class="credit-term-number"></strong>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-move="-1">↑</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-move="1">↓</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove>Sil</button>
                </div>
            </div>
            <div class="row">
                @foreach(['az'=>'AZ','en'=>'EN','ru'=>'RU'] as $locale => $label)
                    <div class="col-md-4">
                        <label class="form-label">Qayda {{ $label }}</label>
                        <textarea class="form-control mb-3" rows="5" data-field="content_{{ $locale }}" @required($locale === 'az')></textarea>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('creditTermItems');
    function renumber() {
        list.querySelectorAll('.credit-term-item').forEach((item, index) => {
            item.querySelector('.credit-term-number').textContent = 'Qayda ' + (index + 1);
            item.querySelectorAll('[data-field]').forEach(field => {
                field.name = 'items[' + index + '][' + field.dataset.field + ']';
            });
        });
    }
    document.getElementById('addCreditTerm').addEventListener('click', () => {
        list.appendChild(document.getElementById('creditTermTemplate').content.cloneNode(true));
        renumber();
    });
    list.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove]');
        const move = event.target.closest('[data-move]');
        if (!remove && !move) return;
        const item = event.target.closest('.credit-term-item');
        if (remove) item.remove();
        else if (move.dataset.move === '-1' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
        else if (move.dataset.move === '1' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
        renumber();
    });
    renumber();
});
</script>
@endsection
