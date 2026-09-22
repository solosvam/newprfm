@php
    $html_tag_data = [];
    $title = 'CRM';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'CRM',
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('js_page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('crm-search');
            const result = document.getElementById('crm-search-result');
            let searchedNumber = '';

            input.addEventListener('input', function () {
                let number = input.value.replace(/\D/g, '');

                if (number.indexOf('994') === 0) {
                    number = number.substring(3);
                }

                input.value = number.substring(0, 9);
                result.innerHTML = '';
                searchedNumber = '';

                if (input.value.length !== 9) {
                    return;
                }

                const searchNumber = input.value;
                searchedNumber = searchNumber;
                result.innerHTML = '<div class="text-muted mt-2">Müştəri axtarılır...</div>';

                fetch('{{ route('admin.crm.search') }}?number=' + encodeURIComponent(searchNumber), {
                    headers: {'Accept': 'application/json'}
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (searchedNumber !== searchNumber) {
                            return;
                        }

                        if (data.found) {
                            window.location.href = data.url;
                            return;
                        }

                        result.innerHTML = '<div class="text-danger mt-2">Bu nömrə ilə müştəri tapılmadı.</div>';
                    })
                    .catch(function () {
                        result.innerHTML = '<div class="text-danger mt-2">Axtarış zamanı xəta yarandı.</div>';
                    });
            });
        });
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body py-5">
                <div class="col-lg-7 col-xl-6 mx-auto">
                    <label for="crm-search" class="form-label fw-bold">Telefon nömrəsi ilə axtar</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">994</span>
                        <input id="crm-search"
                               class="form-control"
                               inputmode="numeric"
                               autocomplete="off"
                               maxlength="9"
                               placeholder="50 123 45 67">
                    </div>
                    <div class="form-text">9 rəqəmi yazan kimi müştəri profili avtomatik açılacaq.</div>
                    <div id="crm-search-result"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
