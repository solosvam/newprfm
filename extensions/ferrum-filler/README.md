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
gedişat Ferrum səhifəsinin sol yuxarı küncündə görünür:
1. Sifarişin mənbəyi = ONLAYN.
2. PinKod = FİN → Ferrum loader-i (`.loader-div`) itənə qədər gözlənilir.
   Tapılsa (xanalar `*****`) — **təkrar müştəri**, şəxsi məlumatlar keçilir; tapılmasa — **yeni müştəri**:
   Ad, Soyad, Ata adı, Cinsi, Ş.V. Seriya və Nömrə yazılır.
3. Məhsul — sifarişin yekunu ($price), Ferrum-dakı "Cari Limit məbləği" ($limit) və müştərinin seçdiyi ay ilə:
   `$price ≤ 200`: limit ≥ price → `FC Check up standart {ay} ay`, yoxsa `FC Check up Online VİP standart {ay} ay`;
   `$price > 200`: limit ≥ price → `FC Standart (201-7000) {ay} ay`, yoxsa `FC Online VİP Standart (201-7000) {ay} ay`.
   VİP (+1%) — limit çatmayanda Ferrum müştəriyə tez zəng edir. Yeni müştəridə limit 0 → həmişə VİP.
   Limit PinKod axtarışı ilə avtomatik gəlir (0 da ola bilər). İlkin ödəniş = 0.
4. Mallar: hər məhsul üçün modal — Gözəllik və sağlamlıq / Ətirlər, Ətir dəstləri, "Brend Məhsul Ölçü", qiymət, say.
5. Telefonlar: öz nömrəsi (Mobil, Sahibi "Öz", Əlaqəlilik "Öz"); yeni müştəridə əlavə 2 qohum (Qohum).
   Nömrə `(0XX)-XXX-XX-XX`.
6. İş yeri: adı və əmək haqqı.
7. Sənədlər bölməsinə heç nə yüklənmir.
DevExpress combo-ları: dropdown düyməsi (✕ yox) ilə açılır, siyahı virtual olduğu üçün lazım olsa mətn hərf-hərf yazılıb süzülür.
Radio (Adi / Limitdən istifadə / Check-up) və Qeyd operatorundur. **Müraciət saxlanmır və göndərilmir.**
Sabit mətnlər `config.js` → `FERRUM`-dadır.

## Kredit müqaviləsini sifarişə bağlamaq (`/pasm`)
Ferrum-da `pm.ferrumcapital.az/pasm` cədvəlində hər sətrin çap düyməsinin yanında **Parfumshop-a bağla** düyməsi çıxır
(`pasm.js`, cədvəl yenidən çəkiləndə düymə yenidən qoyulur).
1. Düyməni basın → parfumshop sifariş nömrəsini (PS… və ya ID) yazın → **Yoxla**: sifariş, müştəri və məbləğ göstərilir;
   sifarişdə artıq müqavilə varsa xəbərdarlıq — yenisi köhnəni əvəz edir.
2. **Yüklə** — extension sətrin çap düyməsini özü basır. Ferrum PDF-i SignalR ilə göndərir və
   `window.downloadFileClientSide(file, fileName, format)` çağırır; `pasm-hook.js` (MAIN world) həmin an bu funksiyanı bir dəfəlik
   əvəz edir: PDF yüklənmir, tutulur. Ehtiyat yol: blob/data linkinə `<a download>.click()`. Adi çap dəyişmir.
3. `background.js` PDF-i parfumshop-a göndərir: `POST /admin/ferrum/orders/{id}/contract` (admin sessiyası + CSRF,
   token `GET /admin/ferrum/contract/{ref}` cavabından). Fayl `storage/app/private/contracts/{sifariş}_{PFL-APP-…}.pdf`,
   yol `orders.contract_file`-a yazılır. Admin sifariş səhifəsində "Kredit müqaviləsi" linki (yalnız `ferrum` icazəsi ilə).
Yükləmə və baxış `sensitive_access_logs`-a yazılır. Yalnız taksit sifarişləri, yalnız PDF (fayl `%PDF-` ilə başlamalıdır).

## Kredit müraciətinin statusu
- Ferrum formasını doldurma bitəndə (`fill.js` → `background.js`) parfumshop-da müraciət **Yoxlanılır** olur —
  yalnız "Gözləmədə"dirsə (`POST /admin/ferrum/orders/{id}/filled`). Popup bağlansa da göndərilir.
- Müqavilə (PDF) sifarişə bağlananda müraciət **Təsdiqlənib** olur.
- Digər statuslar (İmtina edilib, Ləğv edilib) və istənilən düzəliş — əl ilə: Hissəli ödəniş müraciətləri və CRM sifariş səhifəsi.
Hər dəyişiklik `credit_status_logs`-a yazılır (kim, haradan: manual / ferrum_fill / contract).
