# parfumshop — görüləcək işlər

Layihə üzrə **yeganə** görüləcək işlər sənədi. Yeni iş əlavə olunanda, iş bitəndə və ya qərar veriləndə yalnız bu fayl yenilənir.
Əvvəlki `docs/order-fulfillment-plan.md` və `docs/backlog.md` bu fayla birləşdirilib silinib.

Son yenilənmə: 29 oktyabr 2026.

---

## 1. Sifariş təminatı (CRM / anbar / kuryer)

- [ ] **Anbar Telegram bildirişləri — qalanlar** (15 oktyabr proddə qoşuldu; sorğu tipi anbarın öz ayarındadır — Telegram seçilən anbara Telegram-la gedir):
  - real qrupda "Qismən", "Dəyişdir", "Rezerv etdim" və xatırlatmanı yoxlamaq (Rufat yoxlayır);
  - botu anbar qruplarında admin etmək ("Mesajları silmək") — hələlik edilmir, anbarın qiymət cavabları qrupda qalır.
- [ ] **SMS — qalan qərarlar və yoxlamalar** (10 oktyabr: jurnal, çatdırılma statusu, balans, "Yola çıxdım" SMS-i və "SMS" səhifəsi hazırdır):
  - `crm_order_accepted` ("CRM-dən operator sifariş qəbul etdikdə") şablonu bazada var, amma heç bir hadisəyə qoşulmayıb (səhifədə "Qoşulmayıb").
    Qərar: göndərilsinmi, yoxsa silinsin. "Təhvil verildi" SMS-i də qərarsızdır. (`website_order_accepted` 10 oktyabrda silindi.)
  - Təsdiq kodu (OTP) mətni kodda qalır (şablon deyil). CRM "yeni şifrə" SMS-i 15 oktyabrda şablona keçdi (`crm_password_reset`, latın hərfləri).
  - `bonus:remind-expiring` (və `bonus:expire`) cədvəli `routes/console.php`-də şərhə alınıb — `bonus_expiring` SMS-i getmir və müddəti
    bitən bonuslar silinmir. Qərar: nə vaxt açılsın.
- [ ] **CRM sifariş səhifəsi — "Proses" tabının davamı** (10 oktyabr yenidən quruldu: nazik zolaq, "növbəti addım", qruplar, yığılan kartlar):
  proddə real sifarişlərlə (10 məhsul × 10 anbar) baxıb düzəltmək; "Təxmini qazanc" yalnız seçilmiş məhsulların alışını çıxır —
  alışı bilinməyən məhsullar varsa rəqəm şişir, düzəldilməlidir; düymə adlarını birləşdirmək ("Yenilə" nəyi yeniləyir).
  Proses tabındakı qazanc xəbərdarlığı ("Qazanc yoxdur" / "Zərərlə") hələlik çatdırılma xərcini və bank komissiyasını
  saymır — "Təxmini qazanc" düzələndə ikisi eyni düsturdan istifadə etməlidir.
- [ ] **Anbar price listləri — qalanlar** (11 oktyabr: import, sütun seçimi, avtomatik və əl ilə uyğunlaşdırma, brend bağlama / yaratma,
  Proses tabında qiymət və axtarış hazırdır — Satışlar → Price listlər):
  - Price listlər panelindən qiyməti hazır götürüb "Anbar seç" etmək (indi panel yalnız "Sorğu göndər" verir, "Anbar seç"-də qiymət əl ilə yazılır);
  - müqayisə hesabatı: yeni price list yüklənəndə köhnə ilə fərqi göstərmək — "X məhsul yeni siyahıda yoxdur", "Y məhsul yeni əlavə olunub"
    (və qiyməti dəyişənlər); saytda yeni məhsul yaratmaq və deaktiv etmək avtomatik deyil — operator seçir;
  - digər anbarların faylları ilə sınamaq (indiyə qədər yalnız bir anbarın 1C çıxarışı ilə yoxlanıb: 1677 sətirdən 834-ü avtomatik bağlandı);
  - brend adlarını rəsmi yazılışa gətirmək: "Van Cleef &amp; Arpels" (HTML kodu qalıb), "Victorias Secret", "Tiffany Co", "Viktor Rolf", "BOND No9", "Mont Blanc".
- [ ] **Sifarişin ünvanı sifarişə köçürülmür.** Sifariş müştərinin ünvan kartına istinad edir (customer_address_id) — müştəri sonradan
  ünvanını dəyişsə və ya silsə, köhnə sifarişlərin ünvanı da dəyişir / itir. Ünvan sifariş yaranan anda sifarişə köçürülməlidir.
- [ ] **Qeyri-müəyyən bank əməliyyatlarının uzlaşdırılması:** kimin ödədiyi bilinməyən köçürməni sifarişə bağlamaq.
- [ ] **Admin kuryer (baş kuryer).** Kuryerlərdən biri admin seçilir:
  - digər kuryerlərin balansındakı pul hərəkətlərini görür — pul haradan gəldi, kimə ödədi;
  - onların balansındakı pulu öz hesabına köçürür. Adi kuryer pulunu köçürə bilmir, pulu yalnız admin kuryer özünə çəkə bilər;
  - sifarişlərin kuryerlə bağlı bütün proseslərini görür: hansı kuryerdə nə var, hansı statusdadır, anbardan götürüb-götürməyib və s.
- [ ] **Ayarlarda "Telegram" bölməsi** (SMS bölməsi kimi): Telegram mesaj şablonları oradan idarə olunsun.
- [ ] **Sifarişi kuryerə yönləndirəndə anbarlar üzrə "pul ver / pul vermə" seçimi.** Məs. 3 anbar var: 2-nə pul ver, 1-nə vermə.
  "Pul ver" seçilən anbarlarda köhnə qayda qalır. "Pul vermə" seçilənlərdə kuryerə pulla bağlı heç nə görünmür, yalnız "Götürdüm" seçilir;
  "Götürdüm" seçilən kimi anbara borc yaranır (kuryer "götürdüm, pul vermədim" seçmiş kimi).

- [ ] **Borclar üçün ayrıca bölmə** — "borclu olduqlarım" və "mənə borcu olanlar". Müştərilər və anbarlar bölməsindən ayrıdır
  (nə müştəriyə, nə anbara aiddir). Açıq sual: bölmənin adı (məs. "Borclar", "Debitor / kreditor", "Qarşı tərəflər").
- [ ] **Stok alışı və öz istehsalımız:**
  - hədiyyə kartları — HK 100, HK 200, HK 500, HK 1000; öz barkodları var, stok kimi alış edilməlidir;
  - öz brendimizin ətirləri də stok kimi alış edilməlidir;
  - öz mallarımızın istehsalı və istehsala gedən xərclər (xammal, qablaşdırma və s.) uçotu.

## 2. Sayt — səbət və push bildirişləri

- [ ] **Admin "Səbətdəki məhsullar" bölməsinin tamlığını yoxlamaq** (`CartsController` var).
- [ ] **Tərk edilmiş səbət üçün email xatırlatma.** Email göndərişi yoxdur.
- [ ] **Web push (OneSignal): abunəçilərin niyə görünmədiyini yoxlamaq** (App ID və domen).
- [ ] **Push üçün teqlər, `push_subscriptions` cədvəli, admin statistikası, "test push" düyməsi.** Heç biri yoxdur.
- [ ] **Tərk edilmiş səbət və endirimlər üçün push.** Hazırda push yalnız "Qiymət enəndə xəbər ver" üçün gedir.
- [ ] **Müştərinin son aktivliyi və passiv müştərilər.** Müştərilər `remember_token` ilə daxil qalır, logout olmurlar,
  ona görə yalnız login anını saxlamaq kifayət deyil. `customers` cədvəlinə son aktivlik vaxtı (`last_seen_at`) əlavə olunur:
  login olmuş müştərinin sayta hər girişində yenilənir, amma gündə ən çox bir dəfə (son yazılandan 1 gündən çox keçibsə).
  Ayrı-ayrı səhifə keçidləri saxlanılmır. Hazırda belə sütun yoxdur.
  Admin paneldə:
  - statistika: son 10 gündə, 1 ayda və s. sayta girməyən müştərilərin sayı;
  - ayrıca səhifə: həmin müştərilərin siyahısı, müddətə görə filtr, seçilənlərə push və ya SMS göndərmək.
  Push üçün müştərini abunəsinə bağlamaq lazımdır (`push_subscriptions` hələ yoxdur — yuxarıdakı push bəndi ilə əlaqəli).
- [ ] **Tester ətirlərin adı.** Saytda şəklin sol yuxarı küncündə 45° "Tester" lenti 15 oktyabrda edildi (Tester kateqoriyası və ya adda "tester").
  Qalan: adına "Tester" avtomatik əlavə olunsunmu — onda köhnə adlardakı "Tester" / "TESTER" / "L EDP Tester" yazılışları təmizlənməlidir.
- [ ] **Email abunəliyi (yeniliklərdən xəbərdar olmaq):**
  - qonaqlar saytda email yazıb abunə olur;
  - yazılan email köhnə (mövcud) müştəriyə aiddirsə, həmin müştəri avtomatik abunə sayılır;
  - qeydiyyat formasında abunəlik checkbox-u;
  - admin paneldə abunə olanların siyahısı;
  - admin paneldən abunəçilərə email bildirişlərinin göndərilməsi.

## 3. Brauzer extension-ları

- [ ] **ferrum-filler** (`extensions/ferrum-filler/README.md`): real Ferrum formasında yoxlayıb tamamlamaq.

- [ ] **ferrum-filler: kredit müqaviləsini (PDF) sifarişə bağlamaq — real Ferrum-da yoxlamaq** (28 oktyabr yazıldı, v0.6.0).
  Ferrum `/pasm` cədvəlində hər sətrin çap düyməsinin yanında "Parfumshop-a bağla": sifariş nömrəsi yoxlanır, extension çap düyməsini
  özü basır, `downloadFileClientSide`-ı bir dəfəlik əvəz edib PDF-i tutur, `{sifariş}_{PFL-APP-…}.pdf` adı ilə yükləyir →
  `orders.contract_file`; admin sifariş səhifəsində "Kredit müqaviləsi" linki (`ferrum` icazəsi ilə). Saxta Ferrum səhifəsində yoxlanıb.
  Real Ferrum-da yoxlanmalı: çap düyməsi tapılırmı (mətn / title / class-da "çap" və ya "print" axtarılır), müraciət nömrəsi
  sətirdən düz götürülürmü, PDF hansı formatda gəlir. Düymə görünməsə — sətrin HTML-i lazımdır.

## 4. Prod-a çıxmazdan əvvəl

- [ ] Serverdə `schedule:run` cron və queue worker (`QUEUE_CONNECTION=database`; `NotifyPriceDrop` və növbəli email-lər üçün).
  Cron olmasa `payments:check-birbank` da işləmir — bankdan qayıtmayan müştərinin ödənişi yenə "gözləyir" qalar.
- [ ] `.env`-də `ONESIGNAL_REST_API_KEY` (olmasa push getmir).
- [ ] `.env`-də `SHORT_URL=https://paf.az` (sonra `php artisan config:cache`). Olmasa ödəniş, referal və anbar linkləri əsas domenlə gedir.
- [ ] Migrasiyaları işə salmaq.
- [ ] **Test məlumatını silmək, sonra sıfırlama səhifəsini koddan çıxarmaq.** `admin/reset` (menyuda yoxdur, yalnız Super admin): kassa / pul hərəkətləri, sifarişlər və asan sifarişlər, price listlər. Əvvəl bazanın ehtiyat nüsxəsi. İş bitəndən sonra `ResetController`, `ProductionReset`, `backend/reset.blade.php`, testi və `routes/admin.php`-dəki iki sətir silinməlidir.
- [ ] `composer install` (price list importu üçün `phpoffice/phpspreadsheet` əlavə olunub).
- [ ] Cron `sms:check-delivery`-ni də işlədir (5 dəqiqədən bir): göndərilən SMS-lərin çatdırılma statusu.
- [ ] **Test rejimində yoxlamaq:** Birbank taksit seçəndə bankın səhifəsində ayların (2 / 3 / 6) göründüyünü — 11 oktyabrdan ay `description: "TAKSIT=N"` ilə göndərilir, əvvəl göndərilmirdi.
- [ ] **Test rejimində yoxlamaq:** Birbank ödəniş səhifəsini bağlayıb "Ödənişə davam et" ilə eyni səhifənin açıldığını (bank eyni sifarişi təkrar açmağa icazə verirmi)
  və ödənilməyən sifarişin bankda nə vaxt `Expired` olduğunu (rəsmi sənəddə — pg.kapitalbank.az/docs — müddət yazılmayıb;
  status adları oradan təsdiqlənib: Preparing, Cancelled, Rejected, Refused, Expired, Authorized, PartPaid, FullyPaid, Funded, Declined, Voided, Refunded, Closed).
- [ ] `php artisan brands:optimize-logos` — serverdəki brend loqolarını kiçiltmək (lokalda edilib).
- [ ] `php artisan seo:robots` (robots.txt-ə sitemap sətri) və sitemap-i Google Search Console-a göndərmək.

## 5. Admin paneldən sahibkarın edəcəyi işlər

- [ ] FAQ suallarını yeniləmək.
- [ ] Admin → Kredit şərtləri: 5-ci və 6-cı bəndlər təkrardır (min 100 AZN); 8-ci bənddə "6–15 ay faizli" yazılıb, halbuki 6 ay 0%-dir.
- [ ] İstifadə şərtləri və Məxfilik siyasəti mətnini hüquqi baxımdan yoxlatmaq.

## 6. Ən sonda — Influencer proqramı (`influencer.parfumshop.az`)

Meta tərəfi hazırdır, Instagram girişi testdən keçib. Laravel-də hələ heç nə yoxdur.

- [ ] Callback (token və `/me` sorğusu), deauthorize və data deletion route-ları.
- [ ] Portal: landing, müraciət forması, panel, linklər, endirim kodları, admin hissəsi.
- [ ] Brend faizləri, influencerə xüsusi əlavə faiz, komissiyalar.
- [ ] `paf.az` qısa link sistemi və cookie ilə izləmə. Cookie müddəti admin ayarı olacaq (referal ayarları tabında).
  Domen artıq yönləndirici kimi işləyir (`App\Support\ShortUrl`, `RedirectShortDomain`): influencer linkləri eyni domenə əlavə olunacaq.
- [ ] Live-a keçid: tətbiqi düzgün Business portfolio-ya köçürmək, Business Verification, App Review.

## 7. Köhnə saytdan gələn məhsulların təmizlənməsi

Nə ediləcəyi hələ qərarlaşdırılmayıb. Rəqəmlər lokal bazadandır (9 oktyabr, yarımçıq importdan sonra); prodda tam importdan sonra yenidən sayılmalıdır.

- [ ] **Adında "Shower Gel" olan məhsullar.** Lokalda 15 məhsul, hamısı "Digər" tipində (köhnədə `parfum_type` boşdur),
  məs. "Coco Noir Shower Gel L 200ml", "Invictus Shower Gel m 150ml".
- [ ] **Adında "ml" olan məhsullar.** Lokalda 157 məhsul (3-ü aktiv). Həcm, cins (L / m) və dəst məlumatı ölçüdə yox, adın içindədir,
  ölçü isə "Standart" və ya "SET" olur: məs. "ZEN m Gift Set 2pc 50ml", "Gucci By Gucci Sport m Gift Set 3pc 90ml".

---

## Qüvvədə olan qərarlar

- Referal endirimi yalnız sayt sifarişlərinə şamil olunur, CRM sifarişlərinə yox.
- Bonus balansı ayrıca, **tam** ödəniş üsuludur. Qismən bonusla ödəniş olmayacaq.
- Bonus yalnız sifariş təhvil veriləndə qazanılır (bonus balansı və öz kreditimizlə ödənişlərdən başqa). Sifarişin bonus faizi yaradılarkən sabitlənir.
- Köhnə müştərilərin (~12 min) bonusları hələlik qalır. Sıfırlanması barədə qərar sonra veriləcək — o vaxta qədər heç nə edilmir.
- Müştəri ehtiyat nömrəsini (`mobile_2`) saytda görmür və dəyişmir.
- Öz kreditimizlə (hissə-hissə) alışda məhsul endirimi tətbiq olunmur. Birbank taksitində tətbiq olunur. Birbank taksiti "hissə-hissə" sayılmır.
- Promo kod endirimli məhsullara tətbiq olunmur.
- CRM sifariş səhifəsi iş axınına görə qurulub: "Proses" tabında məhsullar bu sıra ilə gəlir — cavab gəlib (seçim gözləyir) →
  cavab gözləyir → anbar seçilib (yığılır, problemlilər qrupun əvvəlində). Dəyişməyən məlumat "Məlumat" tabındadır.
  Admin görünüşlərində Acorn şablonunun hazır komponentləri işlədilir (`~/PhpstormProjects/acorn`), əlavə CSS yazılmır.
- Müştəri sifarişdən saytdan özü imtina edə bilir: yalnız "Sifariş verildi", "Hazırlanır", "Anbarlara sorğu göndərildi" mərhələlərində
  və hələ heç bir anbar seçilməyibsə. Ödənilmiş sifarişdə pul avtomatik qayıtmır — "Karta qaytarılacaq" yaranır, operator qaytarır
  (bonusla ödənilibsə bonus dərhal qayıdır). Sonrakı mərhələlərdə və öz kreditimizlə sifarişdə — "bizimlə əlaqə saxlayın".
- Operator sifarişi ləğv edəndə müştəriyə SMS gedir (`crm_order_cancelled`); müştəri özü imtina edəndə SMS getmir.
- Yarımçıq qalmış Birbank ödənişi: müştəri "Ödənişə davam et" basanda bankdan soruşulur — sifariş bankda hələ açıqdırsa
  (`Preparing`) eyni bank səhifəsinə qaytarılır (yeni ödəniş yaranmır), bağlanıbsa yeni ödəniş başlayır. CRM-də "Bankdan yoxla" eyni yoxlamanı edir.
- Müştəriyə və anbara göndərilən linklər qısa domenlədir (`paf.az`): SMS ödəniş linki, referal linki, anbar portalı linki.
  `paf.az` yalnız yönləndiricidir — eyni yolu əsas sayta ötürür; səhifələr, sessiya və bankın geri qaytarması əsas saytdadır.
- SMS ödəniş linkinin müddəti var: admin ayarı (Ayarlar → Sifariş və çatdırılma, standart 72 saat). Müddət link yarananda başlayır,
  SMS göndəriləndə və ya CRM-də "Müddəti yenilə" basılanda yenidən sayılır. Vaxtı bitmiş link sifarişin məlumatını göstərmir.
- Gözləyən Birbank ödənişi öz vaxt həddimizlə ləğv edilmir: `payments:check-birbank` hər dəqiqə bankdan soruşur,
  ödənilməyən sifarişi bank özü `Expired` edir. Bankda hələ açıq olan ödənişi ləğv saysaq, müştəri iki dəfə ödəyə bilər.
- ps-side üçün Instagram skripti olmayacaq (8 oktyabr qərarı).
- ps-side axtarışında mənbə nişanları (qısaltma / adi / hər ikisi) hələlik qalır.

## Sifariş təminatı və hesablaşma qaydaları

- Müştəriyə ümumi mərhələlər görünür; anbar sorğuları və alış qiymətləri daxili məlumatdır.
- Bir sifariş məhsulunun miqdarı bir neçə anbara bölünə bilər.
- Anbar cavabı, operatorun seçimi, rezervasiya təsdiqi və faktiki götürmə ayrı hadisələrdir.
- Sorğuya cavab gəlməməsi "yoxdur" demək deyil. Köhnə cavablar silinmir.
- Anbar cavabı: anbara məxsus link; operator telefon/WhatsApp cavabını əl ilə daxil edə bilər. Telegram yoxdur.
- Sifariş boyu bir kuryer işləyir. Kuryer ayrıca model deyil: users və admin guard üzərindən rol/icazə ilə işləyir.
- Anbara rezervasiya bildirişində seçilmiş məhsul, say və kuryer göstərilir.
- Hədiyyəlik bükmə seçilibsə, kuryer ünvanadək qablaşdırıb loqolu çantaya qoyur.
- Tapılmayan məhsul kuryer təyinatından əvvəl müştərinin razılığı ilə ləğv edilir; digər məhsullar çatdırılır.
- Ödənişə daxil olan məhsullar/miqdarlar/məbləğlər ayrıca cədvəldə saxlanılır (`payment_items`), refund sətirləri onlara bağlanır.
- Ləğvdə bonus balansı uyğun məbləğdə azalır və "Sifariş ləğvi" qeydi yazılır.
- `orders` cədvəlinə məhsul və ünvan surəti əlavə edilmir.
- Admin panel Azərbaycan dilindədir: `name_az`.
- Məhsulu götürmək pul ödəmək deyil. Anbar ödənişi faktiki ödəyən, mənbə, məbləğ və təminat hissəsinə bağlanır.
- Kuryerin müsbət hesablaşma qalığı şirkətə borcu, mənfi qalıq şirkətin kuryerə borcudur.
- Mərkəzin anbara birbaşa ödənişi kuryerin balansına yazılmır.
- Nağd kassa, bank, şəxslərlə və kuryerlə hesablaşmalar ayrıdır. Pul transferi təkrar xərc sayılmır.
- Maliyyə Clopos üslubundadır (16 oktyabr). "Yeni əməliyyat": **Məxaric / Mədaxil** (balans + kateqoriya; qarşı tərəf sistemdəki
  "Kənar" hesabıdır) və **Köçürmə** (haradan → haraya; kuryerə — avans, kuryerdən kassaya — təhvil). Anbara ödəniş hesabın səhifəsindən.
- Kateqoriyalar: Mədaxil / Məxaric / Hər ikisi, iki səviyyəli ağac. Sistem kateqoriyaları (Satış, Müştəriyə qaytarma, Anbara ödəniş,
  Anbar pulu qaytardı, Köçürmə, Düzəliş, Digər xərclər) kilidlidir; avtomatik olanlar əl ilə seçilmir. Sahibkar — kateqoriyadır
  (qoyduğu pul Mədaxil, götürdüyü Məxaric; borc qalığı izlənmir). İstifadə olunan kateqoriya silinmir.
- Hesablar: Nağd / Kart / Bank / Şəxs ("Yeni hesab", başlanğıc qalıq — "Düzəliş" mədaxili). Şəxsin qalığı şirkətin pulu sayılmır;
  şəxslə hesablaşma köçürmə ilə: Şəxs → Kassa (mənfi qalıq — borcumuz), Kassa → Şəxs. Şəxs öz cibindən ödəyibsə — Məxaric şəxsdən.
  Qalığı sıfır olan hesab deaktiv edilə bilər; sistem hesabları — yox.
- Maliyyə menyusu: Baxış (hesabların kartları + "Yeni əməliyyat"), Hesablar (cədvəl, "Yarat", deaktivlər), Kateqoriyalar,
  Əməliyyatlar (tarix, Növ / Balans / Kateqoriya siyahıları, "Filtrlər" paneli: açıqlama, sifariş, icraçı, anbar, kuryer, əks olunanlar;
  başlıqda cəm = gələn − çıxan, köçürmə daxil deyil; əks olunanlar standart gizli), Pul axını.
- Pul axını (Maliyyə → "Pul axını"): aylar × kateqoriyalar, ən çox 24 ay. Bütün balanslar = şirkətin hesabları + kuryerlər; gəlir / xərc
  kimin ödəməsindən asılı olmayaraq kateqoriyaya yazılır (şəxsin öz cibindən ödədiyi də xərcdir). Daxili köçürmə həm məxaricdə, həm
  mədaxildə görünür (standart gizli). Şəxslərlə hesablaşma maliyyə nəticəsinə daxil deyil — ayrıca blokdadır. Əks əməliyyat əslindən çıxılır.
- Köhnə "Xərc" əməliyyatları "Digər xərclər" Məxaricinə, sahibkar əməliyyatları "Sahibkar" kateqoriyasına çevrilib (migrasiya).
- Status ID-ləri başqa mənalarla əvəz edilmir.
- Məhsulun qazancı xalis satışdan hesablanır: vahid qiymət − məhsula düşən promokod payı (endirim məhsullara proporsional
  bölünür — ödəniş sətirləri və məhsul ləğvi ilə eyni paylama, `PaymentItemsBuilder::itemShares`). Məhsul ləğv ediləndə
  promokod yenidən hesablanmır. Alış satışdan baha olan anbarı seçmək qadağan deyil, amma operatordan təsdiq soruşulur.
- Göndərilən hər SMS `sms_logs` jurnalına yazılır (`SmsLog::deliver`); təsdiq kodu və şifrə jurnalda `***` ilə gizlədilir.
  Çatdırılma statusu lsim hesabatından 5 dəqiqədən bir yoxlanır, ən çox 2 gün. "Yola çıxdım" SMS-i sifarişə bir dəfə gedir.
- SMS şablonlarında müştəriyə müraciət `{name}` (ad) ilədir; `{fullname}` (ad və soyad) də işləyir — kod hər ikisini doldurur.
- Anbar cavabının mənbəyi yalnız "Operator - Telefon", "Operator - Digər" və ya "Anbar - link" ola bilər. WhatsApp və Telegram
  ləğv olunub (10 oktyabr); köhnə cavablar silinmir, tarixçədə öz adı ilə görünür.
- Price list: sətir bizim varianta (məhsul + ölçü) bağlanır; avtomatik yalnız tək namizəd olanda (brend + ad + növ + tester + həcm, cins yalnız
  seçim üçün). Operatorun seçimi anbar üzrə, brend bağlantısı bütün anbarlar üçün yadda qalır. Qiymət alış qiymətidir, 2 onluğa yuvarlaqlaşdırılır.
  Siyahıdakı qiymət stok təsdiqi deyil — sifarişdə siyahının tarixi ilə göstərilir.
- CRM sifarişinin Proses tabı: məhsul adı "brend ad ölçü növ cins" formatında; "Yeni sorğu", "Price listlər" (aşağıdan açılan panel, yalnız × ilə bağlanır)
  və "Anbar seç" (sorğusuz: operator tək anbarı və qiyməti bilir — sorğu, "var" cavabı və seçim birdən yazılır; sorğu SMS-i getmir, seçim SMS-i gedir).
- "Müştəri ilə əlaqə saxlayın və yeri dəqiqləşdirin": hər müştəri üçün bir xüsusi ünvan (customer_addresses.on_call — bir dəfə yaranır,
  saytda "Ünvanlarım"da və seçimlərdə görünmür, checkout-da müştəriyə verilmir); görüş yeri sifarişdə (orders.meeting_place) —
  kuryer zəngdən sonra yazır, operator da yazır / dəyişir. Yalnız CRM sifarişi və asan sifariş.
