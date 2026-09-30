@if(session('warehouse_link'))
    <div class="card mb-4"><div class="card-body">
        <label class="form-label" for="warehouseAccessUrl">{{ session('warehouse_link.name') }} — göndəriləcək link</label>
        <div class="input-group">
            <input id="warehouseAccessUrl" class="form-control" value="{{ session('warehouse_link.url') }}" readonly>
            <button type="button" class="btn btn-outline-primary" data-copy="#warehouseAccessUrl">Kopyala</button>
        </div>
        <small class="text-muted">Linki anbara WhatsApp və ya SMS ilə göndərin. Anbarın bütün açıq sorğularını göstərir, 7 gün etibarlıdır.</small>
    </div></div>
@endif
