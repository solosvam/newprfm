// Ferrum müqaviləsini parfumshop-a göndərir (pasm.js-dən mesajla).
// Sorğular buradan gedir: Ferrum səhifəsindən birbaşa fetch CORS-a ilişir, burada isə host_permissions kifayətdir.
// Admin sessiyasının cookie-si ilə işləyir (popup kimi); POST üçün CSRF token yoxlama cavabından gəlir.
import { SITES, FERRUM_HOST } from './config.js';

async function currentSite() {
    const { siteId } = await chrome.storage.local.get('siteId');
    return SITES.find((s) => s.id === siteId) || SITES[0];
}

async function request(path, options = {}) {
    const site = await currentSite();
    let response;
    try {
        response = await fetch(site.url + path, {
            credentials: 'include', cache: 'no-store', ...options,
            headers: { Accept: 'application/json', ...(options.headers || {}) },
        });
    } catch {
        return { ok: false, error: `${site.label} ilə əlaqə qurulmadı. İnterneti və extension ayarlarındakı saytı yoxlayın.` };
    }
    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    if (response.status === 401 || (!isJson && response.redirected)) {
        return { ok: false, error: `${site.label} admin panelinə daxil olmamısınız. Eyni Chrome-da adminə daxil olun.` };
    }
    if (response.status === 403) return { ok: false, error: 'Hesabınızın "ferrum" icazəsi yoxdur.' };
    if (response.status === 419) return { ok: false, error: 'Sessiya yeniləndi — sifariş nömrəsini yenidən yoxlayın.' };
    if (response.status === 429) return { ok: false, error: 'Çox sorğu göndərildi. Bir dəqiqə sonra yenidən cəhd edin.' };
    const body = isJson ? await response.json().catch(() => null) : null;
    if (!response.ok || !body) {
        const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
        return { ok: false, error: first || body?.message || `Xəta baş verdi (HTTP ${response.status}).` };
    }
    return { ok: true, data: body, site: site.label };
}

// fin — Ferrum sətrindəki FİN: server müştərinin FİN-i ilə tutuşdurur (fin_match)
function checkOrder(ref, fin = '') {
    return request(`/admin/ferrum/contract/${encodeURIComponent(ref)}` + (fin ? `?fin=${encodeURIComponent(fin)}` : ''));
}

function uploadContract({ orderId, csrf, base64, appNo, fin }) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    const form = new FormData();
    form.append('file', new Blob([bytes], { type: 'application/pdf' }), 'contract.pdf');
    if (appNo) form.append('ferrum_app_no', appNo);
    form.append('ferrum_fin', fin || ''); // server yenidən yoxlayır
    return request(`/admin/ferrum/orders/${Number(orderId)}/contract`, { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf } });
}

// Ferrum forması dolduruldu (fill.js) → parfumshop-da kredit müraciəti "Yoxlanılır" (yalnız "Gözləmədə"dirsə)
async function markFilled(orderId) {
    const check = await checkOrder(String(Number(orderId)));
    if (!check.ok) return check;
    return request(`/admin/ferrum/orders/${Number(orderId)}/filled`, { method: 'POST', headers: { 'X-CSRF-TOKEN': check.data.csrf } });
}

chrome.runtime.onMessage.addListener((message, sender, reply) => {
    // Yalnız öz content script-imiz və yalnız Ferrum səhifəsindən
    if (sender.id !== chrome.runtime.id || !sender.url || new URL(sender.url).host !== FERRUM_HOST) return;
    if (message?.type === 'ferrum-progress' && message.kind === 'filled' && message.orderId) {
        markFilled(message.orderId).catch(() => {});
        return;
    }
    if (message?.type === 'contract-check') {
        checkOrder(String(message.ref || ''), String(message.fin || '')).then(reply);
        return true;
    }
    if (message?.type === 'contract-upload') {
        uploadContract(message).then(reply);
        return true;
    }
});
