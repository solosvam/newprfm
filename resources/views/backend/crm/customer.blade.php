@php
    $html_tag_data = [];
    $title = 'Müştəri profili';
    $breadcrumbs = ["/admin"=> "ParfumShop", route('admin.crm.index') => "CRM", "#"=> $customer->fullname]
@endphp
@extends('backend.layout',[ 'title'=>$title])
@section('css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/vendor/cropper.min.css') }}">
    <link rel="stylesheet" href="{{ asset_v('backend/css/crm-credit-profile.css') }}">
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('backend/css/vendor/select2-bootstrap4.min.css')}}"/>
    <link rel="stylesheet" href="{{ asset_v('backend/css/crm-order.css') }}"/>
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
    <script src="{{ asset_v('frontend/js/vendor/cropper.min.js') }}"></script>
    <script src="{{ asset_v('frontend/js/id-card-cropper.js') }}"></script>
    <script src="{{ asset_v('backend/js/crm-credit-profile.js') }}"></script>
    <script src="{{ asset_v('backend/js/crm.js') }}"></script>
    <script src="{{ asset_v('backend/js/crm-order.js') }}"></script>
@endsection
@section('content')
    @include('components.id-card-crop-dialog')
    <div class="container">
        <!-- Title and Top Buttons Start -->
        <div class="page-title-container">
            <div class="row">
                <!-- Title Start -->
                <div class="col-12 col-sm-4">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{$title}}</h1>
                    @include('backend._layout.breadcrumb',['breadcrumbs'=>$breadcrumbs])
                </div>
                <!-- Title End -->

                <!-- Top Buttons Start -->
                <div class="col-12 col-sm-8 d-flex align-items-start justify-content-end">
                    <!-- Tour Button Start -->
                    <div class="position-relative crm-search-box">
                        <input type="text"
                               id="crm-search"
                               class="form-control form-control-lg"
                               placeholder="Mobil no, Ad Soyad, .FIN"
                               autocomplete="off"
                               data-bs-toggle="popover"
                               data-bs-placement="bottom"
                               data-bs-trigger="focus"
                               data-bs-html="true"
                               data-bs-content="
                                   <ul class='mb-0 ps-3 small'>
                                       <li><b>Mobil no:</b> 0103227575 və ya 994103227575</li>
                                       <li><b>Ad Soyad:</b> boşluqla ayır (Ruf Ibr)</li>
                                       <li><b>FİN:</b> nöqtə ilə başlayan 8 simvol (.A1B2C34)</li>
                                   </ul>
                                   " autofocus>
                        <div id="search-results" class="crm-search-results bg-white border rounded shadow-sm"></div>
                    </div>
                    <!-- Tour Button End -->
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
                                        data-url="{{ route('admin.crm.reset.password', $customer->id) }}">
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
                                @if($customer->mobile_2)
                                    <div class="text-muted" title="Ehtiyat telefon">
                                        <i data-acorn-icon="phone" data-acorn-size="16" class="me-1"></i>
                                        <span class="align-middle">{{ $customer->mobile_2 }} <span class="small">(ehtiyat)</span></span>
                                    </div>
                                @endif
                                @if($customer->sourceLabel())
                                    <div class="text-small text-muted mt-1" title="Müştəri haradan yaranıb">
                                        {{ $customer->sourceLabel() }} · {{ $customer->created_at?->format('d.m.Y') }}
                                    </div>
                                @endif
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
                                            ['Şəxsiyyət vəsiqəsi — ön', $creditProfile?->id_card_front, 'front'],
                                            ['Şəxsiyyət vəsiqəsi — arxa', $creditProfile?->id_card_back, 'back'],
                                    ] as [$label, $image, $side])
                                        <div class="col-12">
                                            <div class="text-muted mb-1" style="font-size:11px;">{{ $label }}</div>
                                            @if($image && auth('admin')->user()?->cannot('crm.id_card'))
                                                @include('backend.crm.partials.id-card-locked')
                                            @elseif($image)
                                                @php $imageUrl = route('admin.crm.id-card', ['customer' => $customer, 'side' => $side, 'v' => \App\Services\IdCard\IdCardStorage::version($image)]); @endphp
                                                <a href="{{ $imageUrl }}" target="_blank">
                                                    <img src="{{ $imageUrl }}" alt="{{ $label }}" class="img-fluid rounded border w-100">
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
                                        'orders'            => ['icon' => 'cart',            'label' => 'Sifarişlər',           'count' => $counts['orders']],
                                        'installment'       => ['icon' => 'cart',            'label' => 'Kredit sifarişlər',    'count' => $counts['installment']],
                                        'balance'           => ['icon' => 'dollar',          'label' => 'Bonus balansı',        'count' => null],
                                        'payments'          => ['icon' => 'dollar',          'label' => 'Onlayn ödəmələr',      'count' => $counts['payments']],
                                        'settings'          => ['icon' => 'settings-1',      'label' => 'Tənzimləmələr',        'count' => null],
                                        'credit-profile'    => ['icon' => 'credit-card',     'label' => 'Kredit profili',       'count' => null],
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
            <div class="modal-dialog modal-xl">
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

        {{-- Sifaris Modal --}}
        <div class="modal fade " id="addOrderModal" data-bs-backdrop="static" data-bs-keyboard="false"
             tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header p-3 bg-danger">
                        <h5 class="modal-title text-white">Yeni sifariş</h5>
                        <button type="button" class="btn-close text-large" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('backend.crm.partials.add-order')
                    </div>
                    <div class="modal-footer justify-content-between p-3">
                        <div>
                            <div class="text-muted small">Yekun məbləğ</div>
                            <div class="fs-5 fw-bold" id="crmOrderFooterTotal">0.00 ₼</div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted small" id="crmOrderHint">Səbətə məhsul əlavə edin</span>
                            <button type="button" class="btn btn-primary" id="crmOrderSubmit" disabled>Sifarişi tamamla</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('backend.crm.partials.create-customer-modal')
@endsection
