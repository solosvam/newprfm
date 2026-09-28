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
            .crm-avatar { width: 110px; height: 110px; font-size: 2rem; }
            .crm-profile-card .card-body { padding: 2rem; }
            .crm-credit-card .card-body { padding: 0; }
            .crm-credit-card .credit-trigger { min-height: 68px; cursor: pointer; }
            .crm-credit-card .credit-trigger.disabled { cursor: default; }
            .crm-credit-card .list-item-heading { font-size: 14px; text-decoration: none; }
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

                {{-- Müştəri kartı --}}
                <div class="card crm-profile-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center flex-column">
                            <div class="d-flex align-items-center flex-column mb-4">
                                <div class="sw-13 position-relative mb-3">
                                    <img src="{{ asset('backend/img/profile/' . ($customer->gender == 0 ? 'female.png' : 'male.png')) }}" class="img-fluid rounded-xl" alt="thumb"/>
                                </div>
                                <div class="h5 mb-1">{{ $customer->fullname }}</div>
                                <div class="text-muted">
                                    <i data-acorn-icon="mobile" data-acorn-size="16" class="me-1"></i>
                                    <span class="align-middle">{{ $customer->mobile }}</span>
                                </div>
                            </div>

                            <div class="d-flex flex-row justify-content-between w-100 w-sm-50 w-xl-100">
                                <button type="button" class="btn btn-outline-primary w-100 me-2">
                                    Bonus: {{ number_format((float) $customer->bonus_balance, 2) }} ₼
                                </button>

                                <button type="button" class="btn btn-outline-danger w-100 me-2" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                                    <i data-acorn-icon="plus" data-acorn-size="16" class="me-1"></i>
                                    Sifariş
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Hissəli ödəniş məlumatları --}}
                @php
                    $creditProfile = $customer->creditProfile;
                    $creditProfileComplete = $creditProfile?->isComplete() ?? false;
                @endphp

                <div class="card d-flex mb-2 crm-credit-card">
                    @if($creditProfileComplete)
                        <div class="d-flex flex-grow-1 credit-trigger"
                             role="button"
                             data-bs-toggle="collapse"
                             data-bs-target="#creditProfileCollapse"
                             aria-expanded="false"
                             aria-controls="creditProfileCollapse">
                            <div class="card-body py-4 d-flex align-items-center w-100">
                                <div class="btn btn-link list-item-heading p-0 text-start flex-grow-1">
                                    Hissəli ödəniş məlumatları
                                </div>
                                <span class="badge bg-success ms-2">Tamamlanıb</span>
                            </div>
                        </div>

                        <div id="creditProfileCollapse" class="collapse">
                            <div class="card-body accordion-content pt-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless align-middle mb-3">
                                        <tbody>
                                        @foreach([
                                                ['Ata adı', $creditProfile?->father_name],
                                                ['Cinsiyyət', (string) $customer->gender === '1' ? 'Kişi' : ((string) $customer->gender === '0' ? 'Qadın' : '-')],
                                                ['FİN', $creditProfile?->fin],
                                                ['Qohum ad', $creditProfile?->relative_1_name],
                                                ['Qohum mobil', $creditProfile?->relative_1_phone],
                                                ['Qohum ad', $creditProfile?->relative_2_name],
                                                ['Qohum mobil', $creditProfile?->relative_2_phone],
                                                ['İş yeri', $creditProfile?->workplace_name],
                                                ['Əmək haqqı', $creditProfile?->salary],
                                                ['Vəzifə', $creditProfile?->position],
                                        ] as [$label, $value])
                                            <tr>
                                                <td class="text-muted ps-0" style="width:45%;">{{ $label }}</td>
                                                <td class="fw-medium pe-0">{{ $value ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-2">
                                    @foreach([
                                            ['Şəxsiyyət vəsiqəsi — ön', $creditProfile?->id_card_front],
                                            ['Şəxsiyyət vəsiqəsi — arxa', $creditProfile?->id_card_back],
                                    ] as [$label, $image])
                                        <div class="col-12">
                                            <div class="text-muted mb-1" style="font-size:11px;">{{ $label }}</div>
                                            @if($image)
                                                <a href="{{ asset('frontend/uploads/customers/' . ltrim($image, '/')) }}" target="_blank">
                                                    <img src="{{ asset('frontend/uploads/customers/' . ltrim($image, '/')) }}" alt="{{ $label }}" class="img-fluid rounded border w-100">
                                                </a>
                                            @else
                                                <div class="text-muted">-</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="d-flex flex-grow-1 credit-trigger disabled">
                            <div class="card-body py-4 d-flex align-items-center w-100">
                                <i data-acorn-icon="user" data-acorn-size="16" class="me-2"></i>
                                <div class="btn btn-link list-item-heading p-0 text-start flex-grow-1">
                                    Hissəli ödəniş məlumatları
                                </div>
                                <span class="badge bg-danger ms-2">Tamamlanmayıb</span>
                            </div>
                        </div>
                    @endif
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

        <div class="modal fade modal-close-out" id="addOrderModal" data-bs-backdrop="static" data-bs-keyboard="false"
             tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header p-3">
                        <h5 class="modal-title">Yeni sifariş</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
