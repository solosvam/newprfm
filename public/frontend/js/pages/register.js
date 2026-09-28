// Qeydiyyat: mobil nömrə maskası
document.addEventListener('DOMContentLoaded', function () {
    var mobileInput = document.getElementById('registerMobile');
    if (mobileInput && window.Inputmask) {
        Inputmask({
            mask: '\\9\\9\\4 99 999 99 99',
            placeholder: '_',
            showMaskOnHover: false,
            showMaskOnFocus: true,
            clearIncomplete: false
        }).mask(mobileInput);
    }
});
