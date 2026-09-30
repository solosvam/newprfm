// Parfumshop → Ferrum: ayarlar və Ferrum formasında seçilən sabit dəyərlər.
export const SITES = [
    { id: 'prod', label: 'parfumshop.az', url: 'https://parfumshop.az' },
    { id: 'test', label: 'Lokal — parfumshop.test', url: 'http://parfumshop.test' },
    { id: 'test-https', label: 'Lokal — parfumshop.test (https)', url: 'https://parfumshop.test' },
    { id: 'local', label: 'Lokal (localhost)', url: 'http://localhost' },
];

export const FERRUM_HOST = 'pm.ferrumcapital.az';

// Ferrum combo-larındakı variantların mətni (ekranda göründüyü kimi; böyük/kiçik hərf fərq etmir)
export const FERRUM = {
    source: 'ONLAYN',                                     // Sifarişin mənbəyi
    product: (months) => `FC Check up standart ${months} ay`, // Məhsul — kredit müddətinə görə
    productMonths: [3, 6],                                // Ferrum-da olan müddətlər
    category: 'Gözəllik və sağlamlıq',                    // Mal → Kateqoriya (siyahıda 2 dənədir — birincisi)
    subcategory: 'Ətirlər, Ətir dəstləri',                // Mal → Alt kateqoriya
    phoneType: 'Mobil',
    relationSelf: 'Call Center (öz)',
    relationRelative: 'Qohum',
    gender: { 1: 'Kişi', 0: 'Qadın' },                    // bizdə customers.gender: 1 kişi, 0 qadın
    documentType: 'SV',                                   // vəsiqənin yalnız ön üzü
    pinTimeout: 15000,                                    // PinKod axtarışı: loader ən çox bu qədər gözlənilir (ms)
};
