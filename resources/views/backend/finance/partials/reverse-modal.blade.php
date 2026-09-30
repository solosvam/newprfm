{{-- Əks əməliyyat: qeyd silinmir, eyni məbləğ əks istiqamətdə yazılır --}}
<div class="modal fade" id="reverseMovement" tabindex="-1" aria-labelledby="reverseMovementTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="">
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="reverseMovementTitle">Əks əməliyyat</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <p class="fw-bold mb-2" data-reverse-title></p>
            <p class="text-muted small">Qeyd silinmir: eyni məbləğ əks istiqamətdə yazılır və qalıqlar əvvəlki vəziyyətə qayıdır.</p>
            <label class="form-label" for="reverseNote">Səbəb</label>
            <textarea id="reverseNote" name="note" rows="2" maxlength="2000" class="form-control" required></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-danger">Əks et</button></div>
    </form></div>
</div>
