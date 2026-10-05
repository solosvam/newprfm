// Qeydiyyat: mobil nömrə maskası + hesabın mövcudluğunun yoxlanması
document.addEventListener('DOMContentLoaded', function () {
    var mobileInput = document.getElementById('registerMobile');
    if (!mobileInput) return;

    var config = {};
    try { config = JSON.parse(document.getElementById('register-config')?.textContent || '{}'); } catch (e) {}
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    var existsBox = document.getElementById('registerMobileExists');
    var loginLink = document.getElementById('registerLoginLink');
    var lastChecked = null;
    var redirectTimer = null;

    function digits() {
        return String(mobileInput.value || '').replace(/\D/g, '');
    }

    function reset() {
        if (redirectTimer) { clearTimeout(redirectTimer); redirectTimer = null; }
        if (existsBox) existsBox.hidden = true;
    }

    // Nömrə tam yazılanda: hesab varsa xəbərdarlıq və 2 saniyədən sonra giriş səhifəsinə yönləndirmə
    function checkMobile() {
        var mobile = digits();
        if (mobile.length !== 12 || mobile === lastChecked || !config.checkUrl) return;
        lastChecked = mobile;

        fetch(config.checkUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ mobile: mobile })
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || digits() !== mobile) return;
                if (!data.exists) { reset(); return; }

                if (loginLink && data.loginUrl) loginLink.href = data.loginUrl;
                if (existsBox) existsBox.hidden = false;
                redirectTimer = setTimeout(function () { window.location.href = data.loginUrl; }, 2000);
            })
            .catch(function () { lastChecked = null; });
    }

    if (window.Inputmask) {
        Inputmask({
            // 994-dən sonra birinci rəqəm 0 ola bilməz (0 basılsa qəbul olunmur, növbəti rəqəm gözlənilir)
            mask: '\\9\\9\\4 N9 999 99 99',
            definitions: { N: { validator: '[1-9]' } },
            placeholder: '_',
            showMaskOnHover: false,
            showMaskOnFocus: true,
            clearIncomplete: false,
            oncomplete: checkMobile,
            onincomplete: reset
        }).mask(mobileInput);
    }

    mobileInput.addEventListener('input', function () {
        if (digits().length === 12) checkMobile(); else { lastChecked = null; reset(); }
    });
    mobileInput.addEventListener('blur', checkMobile);

    // Səhifə yenidən açılanda (məs. old() ilə) nömrə artıq tamdırsa
    checkMobile();
});
