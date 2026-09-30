    <dialog id="idCardCropDialog" class="idcrop" aria-labelledby="idCropTitle"
            data-read-error="{{ __('credit_crop_read_error') }}">
        <div class="idcrop__head">
            <h2 id="idCropTitle" class="idcrop__title">{{ __('credit_crop_title') }}</h2>
            <button type="button" class="idcrop__close" data-crop-cancel aria-label="{{ __('credit_crop_cancel') }}">&times;</button>
        </div>
        <p class="idcrop__hint">{{ __('credit_crop_hint') }}</p>
        <div class="idcrop__stage"><img data-crop-image alt=""></div>
        <div class="idcrop__tools">
            <button type="button" data-crop-rotate="-90" aria-label="{{ __('credit_crop_rotate_left') }}" title="{{ __('credit_crop_rotate_left') }}">&#8634;</button>
            <button type="button" data-crop-rotate="90" aria-label="{{ __('credit_crop_rotate_right') }}" title="{{ __('credit_crop_rotate_right') }}">&#8635;</button>
            <button type="button" data-crop-zoom="0.1" aria-label="{{ __('credit_crop_zoom_in') }}" title="{{ __('credit_crop_zoom_in') }}">+</button>
            <button type="button" data-crop-zoom="-0.1" aria-label="{{ __('credit_crop_zoom_out') }}" title="{{ __('credit_crop_zoom_out') }}">&minus;</button>
        </div>
        <ul class="idcrop__checks" aria-live="polite">
            <li data-check="sharp" data-ok="{{ __('credit_crop_sharp_ok') }}" data-bad="{{ __('credit_crop_sharp_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
            <li data-check="size" data-ok="{{ __('credit_crop_size_ok') }}" data-bad="{{ __('credit_crop_size_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
            <li data-check="light" data-ok="{{ __('credit_crop_light_ok') }}" data-bad="{{ __('credit_crop_light_bad') }}"><span data-check-text>{{ __('credit_crop_checking') }}</span></li>
        </ul>
        <div class="idcrop__actions">
            <button type="button" class="btn btn-light" data-crop-cancel>{{ __('credit_crop_cancel') }}</button>
            <button type="button" class="btn btn-dark" data-crop-confirm
                    data-label="{{ __('credit_crop_confirm') }}" data-label-anyway="{{ __('credit_crop_confirm_anyway') }}">{{ __('credit_crop_confirm') }}</button>
        </div>
    </dialog>
