{{--
  CRM → yeni müştəri (axtarışda nömrə tapılmayanda crm.js açır, nömrəni doldurur).
  Xəta olanda modal yenidən açılır (data-open-on-load), daxil edilənlər qalır.
--}}
@php $createErrors = $errors->getBag('createCustomer'); @endphp
<div class="modal modal-right fade" id="createCustomerModal" tabindex="-1" aria-labelledby="createCustomerTitle" aria-hidden="true"
     @if($createErrors->any()) data-open-on-load @endif>
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.crm.customer.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="createCustomerTitle">Yeni müştəri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button>
            </div>
            <div class="modal-body">
                @if($createErrors->any())
                    <div class="alert alert-danger py-2 small">{{ $createErrors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="ccMobile">Mobil</label>
                    <input id="ccMobile" name="mobile" type="tel" inputmode="numeric" class="form-control @if($createErrors->has('mobile')) is-invalid @endif"
                           value="{{ old('mobile') }}" placeholder="994XXXXXXXXX" maxlength="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ccName">Ad</label>
                    <input id="ccName" name="name" type="text" class="form-control @if($createErrors->has('name')) is-invalid @endif" value="{{ old('name') }}" maxlength="30" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ccSurname">Soyad</label>
                    <input id="ccSurname" name="surname" type="text" class="form-control @if($createErrors->has('surname')) is-invalid @endif" value="{{ old('surname') }}" maxlength="30" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ccEmail">E-poçt <span class="text-muted small">(istəyə görə)</span></label>
                    <input id="ccEmail" name="email" type="email" class="form-control @if($createErrors->has('email')) is-invalid @endif" value="{{ old('email') }}" maxlength="50">
                </div>
                <div class="mb-3">
                    <div class="form-label">Cinsi</div>
                    @foreach([1 => 'Kişi', 0 => 'Qadın'] as $value => $label)
                        <label class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gender" value="{{ $value }}" @checked(old('gender') !== null && (int) old('gender') === $value) required>
                            <span class="form-check-label">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <label class="form-check">
                    <input type="hidden" name="send_password" value="0">
                    <input class="form-check-input" type="checkbox" name="send_password" value="1" @checked(old('send_password', '1') === '1')>
                    <span class="form-check-label">Şifrəni müştəriyə SMS ilə göndər</span>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button>
                <button class="btn btn-primary">Yarat</button>
            </div>
        </form>
    </div>
</div>
