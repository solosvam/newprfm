@php
    $html_tag_data = [];
    $title = 'CRM';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        '#' => 'CRM',
    ];
@endphp

@extends('backend.layout', ['title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-md-6">
                    <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.crm.index') }}" class="row g-2">
                    <div class="col-md-9">
                        <label for="crm-search" class="form-label">Müştəri axtar</label>
                        <input id="crm-search"
                               name="q"
                               value="{{ $search }}"
                               class="form-control"
                               autocomplete="off"
                               placeholder="Ad, soyad, telefon və ya e-poçt">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i data-acorn-icon="search" class="me-1" data-acorn-size="16"></i>
                            Axtar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @if($customers->isEmpty())
                    <div class="text-center text-muted py-5">Müştəri tapılmadı.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Müştəri</th>
                                <th>Telefon</th>
                                <th>E-poçt</th>
                                <th>Bonus balansı</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($customers as $customer)
                                <tr>
                                    <td>{{ $customer->id }}</td>
                                    <td class="fw-semibold">{{ trim($customer->name . ' ' . $customer->surname) }}</td>
                                    <td>{{ $customer->mobile ?: '—' }}</td>
                                    <td>{{ $customer->email ?: '—' }}</td>
                                    <td class="text-success fw-semibold">{{ number_format((float) $customer->bonus_balance, 2) }} ₼</td>
                                    <td>
                                        <span class="badge bg-{{ $customer->active ? 'success' : 'secondary' }}">
                                            {{ $customer->active ? 'Aktiv' : 'Deaktiv' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.crm.show', $customer) }}" class="btn btn-sm btn-outline-primary">
                                            Aç
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        {{ $customers->links('backend.pagination') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
