@php
    $title = 'SMS';
    // Hər şablonda istifadə oluna bilən dəyişənlər
    $variables = [
        'crm_order_accepted' => ['fullname', 'bonus'],
        'website_order_accepted' => ['fullname', 'bonus'],
        'order_sent' => ['fullname', 'order_no', 'total', 'total_bonus'],
        'crm_order_cancelled' => ['fullname', 'order_no'],
        'easy_order_registration_bonus' => ['bonus'],
        'easy_order_registration' => [],
        'order_payment_link' => ['order_no', 'link'],
        'crm_customer_created' => ['fullname', 'password'],
        'warehouse_request' => ['count', 'link', 'warehouse'],
        'warehouse_selected' => ['product', 'quantity', 'link', 'warehouse'],
        'warehouse_cancelled' => ['product', 'quantity', 'warehouse'],
        'bonus_expiring' => ['name', 'amount', 'date'],
    ];
@endphp
@extends('backend.layout', ['title' => $title])
@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6"><h1 class="mb-0 pb-0 display-4" id="title">SMS</h1></div>
            {{-- lsim balansı (5 dəqiqə yadda qalır) --}}
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end gap-3">
                <div class="text-end">
                    <div class="text-muted text-small text-uppercase">SMS balansı</div>
                    @if($balance['value'] !== null)
                        <div class="h4 mb-0 {{ $balance['value'] <= 0 ? 'text-danger' : '' }}">{{ rtrim(rtrim(number_format($balance['value'], 2, '.', ' '), '0'), '.') }}</div>
                    @else
                        <div class="text-danger">{{ $balance['error'] }}</div>
                    @endif
                    <div class="text-muted text-small">{{ $balance['at']->format('d.m H:i') }} · <a href="{{ route('admin.sms-template.index', ['tab' => $tab, 'refresh' => 1]) }}">yenilə</a></div>
                </div>
            </div>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title mb-4">
        @foreach(['templates' => 'Şablonlar', 'log' => 'Jurnal', 'problems' => 'Getməyən və çatmayanlar'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.sms-template.index', $key === 'templates' ? [] : ['tab' => $key]) }}">{{ $label }}@if($key === 'problems' && $problemCount) <span class="badge bg-danger ms-1" title="Son 7 gündə">{{ $problemCount }}</span>@endif</a>
            </li>
        @endforeach
    </ul>

    @if($tab === 'templates')
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
                    {{-- Şablon hansı hadisədə göndərilir; qoşulmayıbsa dəyişmək heç nəyə təsir etmir --}}
                    @if($usage[$template->code] ?? null)
                        <div class="text-muted text-small mb-2">{{ $usage[$template->code] }}</div>
                    @else
                        <div class="mb-2"><span class="badge bg-outline-warning">Qoşulmayıb</span> <span class="text-muted text-small">bu şablonla SMS göndərilmir</span></div>
                    @endif
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
    @else
        <form method="GET" action="{{ route('admin.sms-template.index') }}" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="col-12 col-sm-4 col-lg-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nömrə"></div>
            <div class="col-12 col-sm-5 col-lg-4">
                <select name="context" class="form-select">
                    <option value="">Bütün növlər</option>
                    @foreach($contexts as $code => $name)<option value="{{ $code }}" @selected(request('context') === $code)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
        <div class="card mb-3"><div class="card-body">
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Tarix</th>
                    <th class="text-muted text-small text-uppercase">Nömrə</th>
                    <th class="text-muted text-small text-uppercase">Növ</th>
                    <th class="text-muted text-small text-uppercase">Mətn</th>
                    <th class="text-muted text-small text-uppercase">Göndəriş</th>
                    <th class="text-muted text-small text-uppercase">Çatdırılma</th>
                </tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                        <td class="text-nowrap">{{ $log->msisdn ?: '—' }}</td>
                        <td>{{ $contexts[$log->context] ?? $log->context }}</td>
                        <td class="text-alternate">{{ $log->message }}</td>
                        <td>
                            @if($log->isSent())<span class="badge bg-outline-success">Göndərildi</span>
                            @else<span class="badge bg-danger">Getmədi</span><div class="text-danger text-small">{{ $log->error }}</div>@endif
                        </td>
                        <td>
                            @if(!$log->isSent())<span class="text-muted">—</span>
                            @elseif($log->delivery_status === null)<span class="text-muted">Yoxlanmayıb</span>
                            @else
                                <span class="badge {{ $log->delivery_status === \App\Models\SmsLog::DELIVERED ? 'bg-success' : (in_array($log->delivery_status, \App\Models\SmsLog::DELIVERY_FAILED, true) ? 'bg-danger' : 'bg-outline-warning') }}">{{ \App\Models\SmsLog::DELIVERY[$log->delivery_status] ?? $log->delivery_status }}</span>
                                <div class="text-muted text-small">{{ $log->delivery_checked_at?->format('d.m H:i') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ $tab === 'problems' ? 'Getməyən və ya çatmayan SMS yoxdur.' : 'Hələ SMS yazılmayıb.' }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
        {{-- Səhifələmə: Acorn-da standart Bootstrap "pagination" --}}
        @if($logs->hasPages())
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted text-small">{{ $logs->firstItem() }}–{{ $logs->lastItem() }} / {{ $logs->total() }}</span>
                <ul class="pagination mb-0">
                    <li class="page-item {{ $logs->onFirstPage() ? 'disabled' : '' }}"><a class="page-link" href="{{ $logs->previousPageUrl() ?? '#' }}">Əvvəlki</a></li>
                    <li class="page-item disabled"><span class="page-link">{{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span></li>
                    <li class="page-item {{ $logs->hasMorePages() ? '' : 'disabled' }}"><a class="page-link" href="{{ $logs->nextPageUrl() ?? '#' }}">Növbəti</a></li>
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
@section('js_page')
    <script src="{{ asset_v('backend/js/sms-templates.js') }}"></script>
@endsection
