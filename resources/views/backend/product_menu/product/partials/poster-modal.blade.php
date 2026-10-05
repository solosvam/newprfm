<div class="modal fade" id="productPosterModal" tabindex="-1" aria-labelledby="productPosterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="alert alert-info text-start" data-poster-status role="status" aria-live="polite">Poster hazırlanır...</div>
                <img data-poster-preview class="img-fluid rounded" style="max-height:65vh" alt="Məhsulun qiymət posteri" hidden>
                <textarea data-poster-caption class="form-control mt-3" readonly aria-label="Posterlə göndərilən qiymət mətni" hidden></textarea>
            </div>
            <div class="modal-footer p-3">
                <button type="button" class="btn btn-primary" data-poster-copy disabled>Şəkli kopyala</button>
                <button type="button" class="btn btn-outline-primary" data-poster-copy-text hidden>Qiymət mətnini kopyala</button>
                <a class="btn btn-outline-primary" data-poster-download hidden>PNG endir</a>
            </div>
        </div>
    </div>
</div>
