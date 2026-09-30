# Parfumshop → Ferrum (Chrome extension)

Ferrum Capital-ın API-si yoxdur. Bu extension sifarişin müştəri, kredit və məhsul məlumatlarını
parfumshop admin panelindən çəkir və (forma qoşulandan sonra) pm.ferrumcapital.az formasına doldurur.
Formu **göndərmir** — operator yoxlayıb özü göndərir.

## Quraşdırma
1. Chrome → `chrome://extensions` → sağ yuxarıda **Developer mode** açın.
2. **Load unpacked** → bu qovluğu (`extensions/ferrum-filler`) seçin.
3. Extension ikonunu toolbar-a sabitləyin (📌).

## İstifadə
1. Eyni Chrome-da parfumshop admin panelinə daxil olun (hesabda **ferrum** icazəsi olmalıdır).
2. Extension-u açın → sifariş nömrəsini (PS…) və ya ID-ni yazın → **Çək**.
3. Hər sətrə klik — dəyər kopyalanır. Forma qoşulandan sonra **Ferrum-a doldur**.

Ayarlar (⚙): məlumatın çəkildiyi sayt — parfumshop.az və ya lokal (localhost).

## Təhlükəsizlik
- Parol saxlanmır: admin sessiyasının cookie-si ilə işləyir.
- Məlumat yalnız `chrome.storage.session`-dadır (brauzer bağlananda silinir), **Təmizlə** dərhal silir.
- Hər çəkiliş serverdə `sensitive_access_logs` cədvəlinə yazılır (kim, hansı sifariş, nə vaxt).

## Ferrum-a doldurma
Ferrum-da **Əlavə et** ilə boş müraciət açın → popup-da sifarişi çəkin → **Ferrum-a doldur**.
Addımlar (`fill.js`, xanalar label mətninə görə tapılır — Ferrum-un id-ləri hər dəfə dəyişir),
gedişat popup-da və Ferrum səhifəsinin sol yuxarı küncündə görünür:
1. Sifarişin mənbəyi = ONLAYN.
2. PinKod = FİN → blur → Ferrum loader-i (`.loader-div`) itənə qədər gözlənilir. Müştəri tapılsa (xanalar `*****`)
   şəxsi məlumatlar keçilir, tapılmasa Ad, Soyad, Ata adı, Cinsi, Ş.V. Seriya və Nömrə yazılır.
3. Məhsul = `FC Check up standart {3|6} ay` (Müddət özü dolur), İlkin ödəniş = 0.
4. Mallar: hər məhsul üçün modal — Gözəllik və sağlamlıq / Ətirlər, Ətir dəstləri, "Brend Məhsul Ölçü", qiymət, say.
5. Telefonlar: öz nömrəsi (Call Center (öz), sahibi — müştəri), 2 qohum (Qohum). Nömrə `(0XX)-XXX-XX-XX`.
6. İş yeri: adı və əmək haqqı.
7. Sənəd: SV — vəsiqənin ön üzü, PNG-yə çevrilir (Ferrum webp qəbul etmir).
   Qeyd: Ferrum-un yükləmə ünvanı daxili IP-dir (`http://172.16.30.17:7000/api/Upload/UploadFile/`) — "Fayl adı"nın
   dolması faylın çatdığını göstərmir (əl ilə seçəndə də eynidir).
DevExpress combo-ları: dropdown düyməsi (✕ yox) ilə açılır, siyahı virtual olduğu üçün lazım olsa mətn hərf-hərf yazılıb süzülür.
Radio (Adi / Limitdən istifadə / Check-up) və Qeyd operatorundur. **Müraciət saxlanmır və göndərilmir.**
Sabit mətnlər `config.js` → `FERRUM`-dadır.
