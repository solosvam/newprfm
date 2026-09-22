@php
    $html_tag_data = [];
    $fullName = trim($customer->name . ' ' . $customer->surname);
    $title = 'Müştəri profili';
    $breadcrumbs = [
        '/admin' => 'ParfumShop',
        route('admin.crm.index') => 'CRM',
        '#' => $fullName,
    ];
    $initials = mb_strtoupper(mb_substr($customer->name, 0, 1) . mb_substr($customer->surname, 0, 1));
@endphp

@extends('backend.layout', ['title' => $title])

@section('css')
    <style>
        .crm-avatar { width: 112px; height: 112px; font-size: 2rem; }
        .crm-profile-card { min-height: 320px; }
        .crm-tab-content { min-height: 330px; }
        .crm-tabs { border-bottom: 1px solid #e5e7eb; flex-wrap: nowrap; overflow-x: auto; }
        .crm-tabs .nav-link { border: 0 !important; border-bottom: 4px solid transparent !important; border-radius: 0 !important; background: transparent !important; color: #5a5a5a !important; font-size: 1.15rem; padding: 1.2rem 1.35rem 1rem; white-space: nowrap; }
        .crm-tabs .nav-link:hover { color: #1fa9e6 !important; }
        .crm-tabs .nav-link.active { border-bottom-color: #20a9e5 !important; color: #20a9e5 !important; }
    </style>
@endsection

@section('js_page')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.getElementById('crmTabs');
            const tabContent = document.getElementById('crmTabContent');
            const tabUrl = tabs.dataset.tabUrl;
            const searchInput = document.getElementById('crm-search');
            const searchResult = document.getElementById('crm-search-result');
            let searchedNumber = '';

            searchInput.addEventListener('input', function () {
                let number = searchInput.value.replace(/\D/g, '');

                if (number.indexOf('994') === 0) {
                    number = number.substring(3);
                }

                searchInput.value = number.substring(0, 9);
                searchResult.innerHTML = '';
                searchedNumber = '';

                if (searchInput.value.length !== 9) {
                    return;
                }

                const searchNumber = searchInput.value;
                searchedNumber = searchNumber;

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

                        searchResult.innerHTML = '<div class="text-danger small mt-1">Müştəri tapılmadı.</div>';
                    })
                    .catch(function () {
                        searchResult.innerHTML = '<div class="text-danger small mt-1">Axtarış zamanı xəta yarandı.</div>';
                    });
            });

            function loadTab(tab, url) {
                tabContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

                fetch(url || tabUrl.replace('__TAB__', tab), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error();
                        }

                        return response.text();
                    })
                    .then(function (html) {
                        tabContent.innerHTML = html;
                        new AcornIcons().replace();
                    })
                    .catch(function () {
                        tabContent.innerHTML = '<div class="text-center text-danger py-5">Məlumatları yükləmək mümkün olmadı.</div>';
                    });
            }

            tabs.addEventListener('click', function (event) {
                const tab = event.target.closest('[data-tab]');

                if (!tab) {
                    return;
                }

                event.preventDefault();
                tabs.querySelectorAll('[data-tab]').forEach(function (item) {
                    item.classList.remove('active');
                });
                tab.classList.add('active');
                loadTab(tab.dataset.tab);
            });

            tabContent.addEventListener('click', function (event) {
                const page = event.target.closest('.pagination a');

                if (!page) {
                    return;
                }

                event.preventDefault();
                loadTab(null, page.href);
            });

            const smsModal = document.getElementById('smsModal');

            smsModal.addEventListener('show.bs.modal', function () {
                const body = document.getElementById('smsModalBody');
                body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

                fetch(smsModal.dataset.url, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error();
                        }

                        return response.text();
                    })
                    .then(function (html) {
                        body.innerHTML = html;
                    })
                    .catch(function () {
                        body.innerHTML = '<div class="text-danger">SMS tarixçəsi yüklənə bilmədi.</div>';
                    });
            });

            const orderModal = document.getElementById('orderModal');

            orderModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const body = document.getElementById('orderModalBody');

                body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

                fetch(button.dataset.url, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error();
                        }

                        return response.text();
                    })
                    .then(function (html) {
                        body.innerHTML = html;
                    })
                    .catch(function () {
                        body.innerHTML = '<div class="text-danger">Sifariş detalları yüklənə bilmədi.</div>';
                    });
            });

            loadTab('orders');
        });
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-md-7">
                    <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-md-5 mt-3 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text">994</span>
                        <input id="crm-search" class="form-control" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="Müştəri nömrəsi">
                    </div>
                    <div id="crm-search-result"></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="h4 mb-0">#{{ $customer->id }} {{ $fullName }}</span>
                    <span class="badge bg-{{ $customer->active ? 'success' : 'secondary' }} ms-2">
                        {{ $customer->active ? 'Aktiv' : 'Deaktiv' }}
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#smsModal">
                        <i data-acorn-icon="message" class="me-1" data-acorn-size="16"></i>
                        SMS-lər
                    </button>
                    <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                        <i data-acorn-icon="lock-off" class="me-1" data-acorn-size="16"></i>
                        Şifrə yenilə
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-3">
                <div class="card crm-profile-card h-100">
                    <div class="card-body text-center d-flex flex-column align-items-center">
                        <div class="crm-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3">
                            {{ $initials ?: '?' }}
                        </div>
                        <h4 class="mb-1">#{{ $customer->id }} {{ $fullName }}</h4>
                        <div class="text-muted mb-1">{{ $customer->mobile ?: 'Telefon qeyd edilməyib' }}</div>
                        <div class="text-muted text-break">{{ $customer->email ?: 'E-poçt qeyd edilməyib' }}</div>
                        <div class="row w-100 mt-auto pt-4">
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="text-muted small">Sifariş</div>
                                    <div class="fw-bold">{{ $customer->orders_count }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="text-muted small">Bonus</div>
                                    <div class="fw-bold text-success">{{ number_format((float) $customer->bonus_balance, 2) }} ₼</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="card h-100">
                    <div class="card-body p-0">
                        <ul class="nav crm-tabs px-3" id="crmTabs"
                            data-tab-url="{{ route('admin.crm.tab', ['customer' => $customer->id, 'tab' => '__TAB__']) }}">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-tab="orders">
                                    <i data-acorn-icon="cart" class="me-1" data-acorn-size="16"></i>Sifarişlər
                                    <span class="badge bg-primary ms-1">{{ $customer->orders_count }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-tab="payments">
                                    <i data-acorn-icon="dollar" class="me-1" data-acorn-size="16"></i>Ödənişlər
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-tab="bonuses">
                                    <i data-acorn-icon="gift" class="me-1" data-acorn-size="16"></i>Bonus balansı
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-tab="settings">
                                    <i data-acorn-icon="gear" class="me-1" data-acorn-size="16"></i>Tənzimləmələr
                                </a>
                            </li>
                        </ul>
                        <div class="crm-tab-content" id="crmTabContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Şifrəni yenilə</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Yeni şifrə yaradılacaq və {{ $customer->mobile }} nömrəsinə SMS ilə göndəriləcək. Davam edək?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Ləğv et</button>
                    <form method="POST" action="{{ route('admin.crm.reset-password', $customer) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning">Şifrəni sıfırla</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="smsModal" tabindex="-1" aria-hidden="true"
         data-url="{{ route('admin.crm.sms', $customer) }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">SMS-lər</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="smsModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Bağla</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sifariş detalları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Bağla</button>
                </div>
            </div>
        </div>
    </div>
@endsection
