// Qeydiyyat: mobil nömrə maskası
document.addEventListener('DOMContentLoaded', function () {
    var mobileInput = document.getElementById('registerMobile');
    if (mobileInput && window.Inputmask) {
        Inputmask({
            // 994-dən sonra birinci rəqəm 0 ola bilməz (0 basılsa qəbul olunmur, növbəti rəqəm gözlənilir)
            mask: '\\9\\9\\4 N9 999 99 99',
            definitions: { N: { validator: '[1-9]' } },
            placeholder: '_',
            showMaskOnHover: false,
            showMaskOnFocus: true,
            clearIncomplete: false
        }).mask(mobileInput);
    }
});
