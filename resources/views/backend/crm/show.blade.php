@php
    $html_tag_data = [];
    $title = 'Müştəri profili';
    $breadcrumbs = ["/admin"=> "ParfumShop", route('admin.crm.index') => "CRM", "#"=> $customer->fullname]
@endphp
@extends('backend.layout',[ 'title'=>$title])
@section('css')
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2-bootstrap4.min.css')}}"/>
        <style>
            .crm-avatar { width: 80px; height: 80px; font-size: 1.5rem; }
            .credit-profile-card { border: 1px solid var(--separator); overflow: hidden; }
            .credit-profile-card .accordion-button { min-height: 52px; background: transparent; box-shadow: none; }
            .credit-profile-card .accordion-button:not(.collapsed) { background: rgba(var(--primary-rgb), .04); }
            .credit-profile-card .credit-profile-title { font-size: 14px; font-weight: 600; }
        </style>
@endsection

@section('js_page')
    <script src="{{asset('backend/js/vendor/select2.full.min.js')}}"></script>
    <script src="{{asset('backend/js/forms/controls.select2.js')}}"></script>
    <script src="{{asset('backend/js/crm.js')}}"></script>
    <script src="{{asset('backend/js/international/crm.int.js')}}"></script>
@endsection
@section('content')
    <div class="container">
        <!-- Title and Top Buttons Start -->
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-md-7">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-md-5 d-flex align-items-start justify-content-end">

                </div>
                <!-- Top Buttons End -->
            </div>
        </div>
        <!-- Title and Top Buttons End -->

        <div class="row gx-4 gy-3">

            {{-- Action Buttons --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0 fw-bold">{{ $customer->fullname }}</h5>
                                @if($customer->active)
                                    <span class="badge bg-success">Aktiv</span>
                                @else
                                    <span class="badge bg-danger">Deaktiv</span>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2">

                                <button class="btn btn-sm btn-outline-warning" id="resetPasswordBtn"
                                        data-url="">
                                    <i data-acorn-icon="lock-off" data-acorn-size="15" class="me-1"></i> Şifrə yenilə
                                </button>

                                <button class="btn btn-sm btn-outline-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#smsModal"
                                        data-url="{{ route('admin.crm.sms', $customer->id) }}">
                                    <i data-acorn-icon="message" data-acorn-size="15" class="me-1"></i> SMS-lər
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sol Panel --}}
            <div class="col-12 col-xl-3">

                {{-- Avatar + Balans --}}
                <div class="card mb-3">
                    <div class="card-body text-center pb-2">
                        <div class="position-relative d-inline-block mb-2">
                            <div class="crm-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3">
                                Rİ
                            </div>
                        </div>
                        <h6 class="fw-bold mb-0">{{ $customer->fullname }}</h6>
                        <small class="text-muted d-block">
                            <i data-acorn-icon="mobile" data-acorn-size="13" class="me-1"></i>{{ $customer->mobile }}
                        </small>

                        <div class="mt-2">
                            <button class="btn btn-outline-success w-100 balance-tab-btn">
                                <div class="small text-muted">BONUS</div>
                                <div class="fw-bold">{{ $customer->bonus_balance }} ₼</div>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Ətraflı məlumatlar --}}
                @php
                    $creditProfile = $customer->creditProfile;
                    $creditProfileComplete = $creditProfile?->isComplete() ?? false;
                @endphp
                <div class="card credit-profile-card">
                    <div class="card-body p-0">
                        <div class="accordion accordion-flush" id="customerDetails">
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    @if($creditProfileComplete)
                                        <button class="accordion-button collapsed px-3 py-3" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#detailsCollapse"
                                                aria-expanded="false" aria-controls="detailsCollapse">
                                            <i data-acorn-icon="user" data-acorn-size="16" class="me-2"></i>
                                            <span class="credit-profile-title flex-grow-1">Hissəli ödəniş məlumatları</span>
                                            <span class="badge bg-success me-2">Tamamlanıb</span>
                                        </button>
                                    @else
                                        <div class="d-flex align-items-center px-3 py-3">
                                            <i data-acorn-icon="user" data-acorn-size="16" class="me-2"></i>
                                            <span class="credit-profile-title flex-grow-1">Hissəli ödəniş məlumatları</span>
                                            <span class="badge bg-danger">Tamamlanmayıb</span>
                                        </div>
                                    @endif
                                </h2>

                                @if($creditProfileComplete)
                                    <div id="detailsCollapse" class="accordion-collapse collapse">
                                        <div class="accordion-body border-top pt-3 px-3 pb-3">
                                            <div class="row g-2">
                                                @foreach([
                                                        ['ATA ADI', $creditProfile?->father_name],
                                                        ['CİNSİYYƏT', $customer->gender],
                                                        ['FİN', $creditProfile?->fin],
                                                        ['1-Cİ QOHUMUN ADI', $creditProfile?->relative_1_name],
                                                        ['1-Cİ QOHUMUN NÖMRƏSİ', $creditProfile?->relative_1_phone],
                                                        ['2-Cİ QOHUMUN ADI', $creditProfile?->relative_2_name],
                                                        ['2-Cİ QOHUMUN NÖMRƏSİ', $creditProfile?->relative_2_phone],
                                                        ['ŞƏXSİYYƏT VƏSİQƏSİ (ÖN)', $creditProfile?->id_card_front],
                                                        ['ŞƏXSİYYƏT VƏSİQƏSİ (ARXA)', $creditProfile?->id_card_back],
                                                        ['İŞ YERİNİN ADI', $creditProfile?->workplace_name],
                                                        ['ƏMƏK HAQQI', $creditProfile?->salary],
                                                        ['VƏZİFƏ', $creditProfile?->position],
                                                ] as [$label, $value])
                                                    <div class="col-12">
                                                        <div style="font-size:11px;" class="text-muted">{{ $label }}</div>
                                                        <div style="font-size:13px;" class="fw-medium">{{ $value ?: '-' }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Sağ Panel --}}
            <div class="col-12 col-xl-9">
                <div class="card">
                    <div class="card-body p-0">

                        {{-- Tab Menyu --}}
                        <div class="border-bottom px-3 pt-3">
                            <ul class="nav nav-tabs nav-tabs-line border-0 flex-nowrap overflow-auto" id="customerTabs"
                                data-customer-id="{{ $customer->id }}">
                                @php
                                    $tabs = [
                                        'orders'        => ['icon' => 'cart',            'label' => 'Sifarişlər',   'count' => null],
                                        'installment'        => ['icon' => 'cart',            'label' => 'Kredit sifarişlər',   'count' => null],
                                        'balance'      => ['icon' => 'dollar',          'label' => 'Bonus balansı',    'count' => null],
                                        'payments'      => ['icon' => 'dollar',          'label' => 'Onlayn ödəmələr',    'count' => null],
                                        'settings'      => ['icon' => 'settings-1',      'label' => 'Tənzimləmələr','count' => null],
                                    ];
                                @endphp
                                @foreach($tabs as $key => $tab)
                                    <li class="nav-item">
                                        <a class="nav-link text-nowrap {{ $loop->first ? 'active' : '' }}"
                                           href="#" data-tab="{{ $key }}" data-type="int">
                                            <i data-acorn-icon="{{ $tab['icon'] }}" data-acorn-size="15"
                                               class="me-1"></i>
                                            {{ $tab['label'] }}
                                            @if(!empty($tab['count']))
                                                <span class="badge bg-secondary ms-1">{{ $tab['count'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Tab Content --}}
                        <div class="p-3" id="tabContent">
                            <div class="text-center text-muted py-5">
                                <i data-acorn-icon="loading" data-acorn-size="30"></i>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>


        {{-- SMS Modal --}}
        <div class="modal fade modal-close-out" id="smsModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header p-3">
                        <h5 class="modal-title">SMS tarixçəsi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="smsModalBody">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
