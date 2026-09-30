@php
    $title = 'SMS şablonları';
    // Hər şablonda istifadə oluna bilən dəyişənlər
    $variables = [
        'crm_order_accepted' => ['fullname', 'bonus'],
        'website_order_accepted' => ['fullname', 'bonus'],
        'order_sent' => ['fullname', 'total', 'total_bonus'],
        'crm_order_cancelled' => ['fullname'],
        'easy_order_registration_bonus' => ['bonus'],
        'easy_order_registration' => [],
        'order_payment_link' => ['order_no', 'link'],
        'crm_customer_created' => ['fullname', 'password'],
        'warehouse_request' => ['count', 'link', 'warehouse'],
        'warehouse_selected' => ['product', 'quantity', 'link', 'warehouse'],
        'warehouse_cancelled' => ['product', 'quantity', 'warehouse'],
    ];
@endphp
@extends('backend.layout', ['title' => $title])
@section('content')
<div class="container">
    <div class="page-title-container"><h1 class="mb-0 pb-0 display-4">SMS şablonları</h1></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="row g-3">
        @foreach($templates as $template)
        <div class="col-12 col-lg-6">
            <div class="card h-100"><div class="card-body p-3">
                <form method="POST" action="{{ route('admin.sms-template.update', $template) }}" class="d-flex flex-column h-100">@csrf
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="mb-0">{{ $template->name }}</h6>
                        <div class="form-check form-switch mb-0">
                            <input type="hidden" name="active" value="0">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="sms-active-{{ $template->id }}" @checked($template->active)>
                            <label class="form-check-label" for="sms-active-{{ $template->id }}">Aktiv</label>
                        </div>
                    </div>
                    <textarea name="template" rows="3" class="form-control sms-template-text" maxlength="1000" required>{{ old('template', $template->template) }}</textarea>
                    @error('template')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2 small mt-auto pt-2">
                        <span>Simvol: <strong class="sms-char-count">0</strong></span>
                        <span>SMS: <strong class="sms-part-count">1</strong></span>
                        @if($variables[$template->code] ?? ['fullname'])
                            <span class="text-muted">
                                @foreach($variables[$template->code] ?? ['fullname'] as $variable)<code>{{ '{'.$variable.'}' }}</code> @endforeach
                            </span>
                        @endif
                        <button class="btn btn-primary btn-sm ms-auto" type="submit">Yadda saxla</button>
                    </div>
                    <div class="sms-limit-warning text-warning small mt-1 d-none"></div>
                </form>
            </div></div>
        </div>
        @endforeach
    </div>
</div>
@endsection
@section('js_page')
    <script src="{{ asset_v('backend/js/sms-templates.js') }}"></script>
@endsection
