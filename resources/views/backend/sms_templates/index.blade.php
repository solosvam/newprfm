@php $title='SMS şablonları'; @endphp
@extends('backend.layout',['title'=>$title])
@section('content')
<div class="container">
    <div class="page-title-container"><h1 class="mb-0 pb-0 display-4">SMS şablonları</h1></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="row">
        @foreach($templates as $template)
        <div class="col-12 mb-4">
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('admin.sms-template.update',$template) }}">@csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">{{ $template->name }}</h5>
                        <div class="form-check form-switch">
                            <input type="hidden" name="active" value="0">
                            <input class="form-check-input" type="checkbox" name="active" value="1" @checked($template->active)>
                            <label class="form-check-label">Aktiv</label>
                        </div>
                    </div>
                    <textarea name="template" rows="4" class="form-control sms-template-text" maxlength="1000" required>{{ old('template',$template->template) }}</textarea>
                    <div class="d-flex gap-3 mt-2 small">
                        <span>Simvol sayı: <strong class="sms-char-count">0</strong></span>
                        <span>SMS sayı: <strong class="sms-part-count">1</strong></span>
                        <span class="sms-limit-warning text-warning d-none">160 simvol keçildi — 2-ci SMS-ə keçdi.</span>
                    </div>
                    @error('template')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                    <div class="form-text mt-2">
                        Dəyişənlər:
                        @if(in_array($template->code,['crm_order_accepted','website_order_accepted'])) <code>{fullname}</code> <code>{bonus}</code>
                        @elseif($template->code==='order_sent') <code>{fullname}</code> <code>{total}</code> <code>{total_bonus}</code>
                        @else <code>{fullname}</code> @endif
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Yadda saxla</button>
                </form>
            </div></div>
        </div>
        @endforeach
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sms-template-text').forEach(function (textarea) {
        const form = textarea.closest('form');
        const charCount = form.querySelector('.sms-char-count');
        const partCount = form.querySelector('.sms-part-count');
        const warning = form.querySelector('.sms-limit-warning');

        function updateSmsCounter() {
            const length = Array.from(textarea.value).length;
            const parts = Math.max(1, Math.ceil(length / 160));
            charCount.textContent = length;
            partCount.textContent = parts;

            if (length > 160) {
                warning.classList.remove('d-none');
                warning.textContent = '160 simvol keçildi — ' + parts + '-ci SMS-ə keçdi.';
            } else {
                warning.classList.add('d-none');
            }
        }

        textarea.addEventListener('input', updateSmsCounter);
        updateSmsCounter();
    });
});
</script>
@endsection
