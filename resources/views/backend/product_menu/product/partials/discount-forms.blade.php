{{-- Endirim tabının formaları (əsas məhsul formasından kənarda; tabdakı sahələr form="…" ilə bağlanır) --}}
<form id="productDiscountForm" method="POST" action="{{ route('admin.product-discounts.store', $product) }}">@csrf</form>
@foreach($product->discounts as $discount)
    @if($discount->status() === 'active')
        <form id="discount-end-{{ $discount->id }}" method="POST" action="{{ route('admin.product-discounts.end', $discount) }}">@csrf</form>
    @endif
    <form id="discount-delete-{{ $discount->id }}" method="POST" action="{{ route('admin.product-discounts.destroy', $discount) }}">@csrf @method('DELETE')</form>
@endforeach

{{-- Endirimi redaktə et: aktivdə faiz və bitmə, planlaşdırılmışda hamısı (ProductDiscountsController::update) --}}
@php
    $editErrors = $errors->getBag('discountEdit');
    $editDiscount = $editErrors->any() ? $product->discounts->firstWhere('id', (int) old('discount_id')) : null;
@endphp
<div class="modal fade" id="discountEditModal" tabindex="-1" aria-labelledby="discountEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="discountEditForm" method="POST"
              action="{{ $editDiscount ? route('admin.product-discounts.update', $editDiscount) : '#' }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="discount_id" value="{{ old('discount_id') }}">
            <div class="modal-header">
                <h5 class="modal-title" id="discountEditTitle">Endirimi redaktə et</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
            </div>
            <div class="modal-body">
                @if($editErrors->any())
                    <div class="alert alert-danger py-2">{{ $editErrors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="discount-edit-percent">Endirim faizi *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="1" max="90" id="discount-edit-percent" name="percent" class="form-control" required value="{{ old('percent') }}">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="discount-edit-starts">Başlama</label>
                        <input type="datetime-local" id="discount-edit-starts" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
                        <div class="form-text" data-starts-hint></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="discount-edit-ends">Bitmə *</label>
                        <input type="datetime-local" id="discount-edit-ends" name="ends_at" class="form-control" required value="{{ old('ends_at') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Ləğv et</button>
                <button type="submit" class="btn btn-primary">Yadda saxla</button>
            </div>
        </form>
    </div>
</div>
<script>
    (() => {
        const modalEl = document.getElementById('discountEditModal');
        const form = document.getElementById('discountEditForm');
        if (!modalEl || !form) return;
        // admin-də Bootstrap 5.0.1 — getOrCreateInstance yoxdur
        const modal = () => bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        const starts = form.querySelector('[name=starts_at]');
        const hint = form.querySelector('[data-starts-hint]');
        const lockStarts = (active) => {
            // aktiv endirimin başlama vaxtı keçib — dəyişmir (server də nəzərə almır)
            starts.disabled = active;
            hint.textContent = active ? 'Endirim artıq başlayıb' : 'Boş və ya keçmiş — dərhal başlayır';
        };
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-discount-edit]');
            if (!button) return;
            form.action = button.dataset.url;
            form.querySelector('[name=discount_id]').value = button.dataset.id;
            form.querySelector('[name=percent]').value = button.dataset.percent;
            starts.value = button.dataset.starts;
            form.querySelector('[name=ends_at]').value = button.dataset.ends;
            lockStarts(button.dataset.status === 'active');
            modal().show();
        });
        @if($editDiscount)
            // xəta ilə qayıdanda pəncərə yenidən açılır
            document.addEventListener('DOMContentLoaded', () => {
                lockStarts(@json($editDiscount->status() === 'active'));
                modal().show();
            });
        @endif
    })();
</script>
