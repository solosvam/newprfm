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
    // Məhsul: sifariş məbləği (≤ limit → Check up, > → Standart) və Ferrum-dakı cari limitə görə.
    // Limit sifarişi qarşılamırsa VİP (+1%): Ferrum müştəriyə tez zəng edir. Yeni müştəridə limit 0 → həmişə VİP.
    products: {
        smallLimit: 200,
        checkup: 'FC Check up standart {m} ay',
        checkupVip: 'FC Check up Online VİP standart {m} ay',
        standard: 'FC Standart (201-7000) {m} ay',
        standardVip: 'FC Online VİP Standart (201-7000) {m} ay',
    },
    category: 'Gözəllik və sağlamlıq',                    // Mal → Kateqoriya (siyahıda 2 dənədir — birincisi)
    subcategory: 'Ətirlər, Ətir dəstləri',                // Mal → Alt kateqoriya
    phoneType: 'Mobil',
    ownerSelf: 'Öz',                                      // müştərinin öz nömrəsi: Sahibi
    relationSelf: 'Öz',                                   // müştərinin öz nömrəsi: Əlaqəlilik
    relationRelative: 'Qohum',
    gender: { 1: 'Kişi', 0: 'Qadın' },                    // bizdə customers.gender: 1 kişi, 0 qadın
    pinTimeout: 15000,                                    // PinKod axtarışı: loader ən çox bu qədər gözlənilir (ms)
};
