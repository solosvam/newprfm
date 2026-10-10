# parfumshop — görüləcək işlər

Layihə üzrə **yeganə** görüləcək işlər sənədi. Yeni iş əlavə olunanda, iş bitəndə və ya qərar veriləndə yalnız bu fayl yenilənir.
Əvvəlki `docs/order-fulfillment-plan.md` və `docs/backlog.md` bu fayla birləşdirilib silinib.

Son yenilənmə: 10 oktyabr 2026.

---

## 1. Sifariş təminatı (CRM / anbar / kuryer)

- [ ] **Anbarlar üçün Telegram ləğv olunur.** Anbar sorğuya yalnız göndərilən linkdən cavab verir.
  Operatorun cavab daxil etməsində "Mənbə" seçimindən Telegram çıxarılır:
  `ProcurementController::recordOffer` validasiyası (`in:phone,whatsapp,telegram,manual`),
  `backend/procurement/order-content` və `backend/crm/partials/order-item-history` görünüşləri.
  Köhnə "telegram" mənbəli cavablar silinmir, tarixçədə əvvəlki kimi görünür.
- [ ] **Kuryer "Yola çıxdım" basanda müştəriyə SMS.** `CourierController::start` → `OrderStatusService::startDelivery`.
  Mətn admin paneldəki SMS şablonlarından redaktə olunur (bazada `order_sent` şablonu var, amma heç yerdə göndərilmir).
  Kuryer düyməni təkrar bassa, ikinci SMS getməməlidir.
  Qeyd: 6 oktyabrda digər statuslar üçün də SMS (qəbul olundu, təhvil verildi, ləğv edildi) danışılıb, qərar verilməyib.
- [ ] **Bütün SMS şablonlarının yoxlanması.** Admin paneldəki hər SMS şablonuna baxıb kodda harada istifadə olunduğunu
  (hansı hadisədə göndərildiyini) və ümumiyyətlə qoşulub-qoşulmadığını müəyyən etmək. Bilinən nümunə: `order_sent`
  bazada var, amma heç yerdə göndərilmir (yuxarıdakı bənd).
- [ ] **SMS jurnalı və çatdırılma statusu.** Göndərilən hər SMS bazaya yazılır (kimə, mətn, şablon, nə vaxt, nəticə)
  və lsim API ilə statusu mütəmadi yoxlanılır (çatdı / çatmadı / gözləyir).
- [ ] **"SMS şablonları" səhifəsi "SMS ayarları"na çevrilir** (ad dəqiqləşəcək): şablonlarla yanaşı lsim API-dən SMS balansı göstərilir.
- [ ] **SMS xətaları bir yerdə görünsün:** göndərilməyən, xəta verən, çatmayan mesajlar həmin səhifədə siyahı ilə.
- [ ] **CRM sifariş səhifəsi — "Proses" tabının davamı** (10 oktyabr yenidən quruldu: nazik zolaq, "növbəti addım", qruplar, yığılan kartlar):
  proddə real sifarişlərlə (10 məhsul × 10 anbar) baxıb düzəltmək; "Təxmini qazanc" yalnız seçilmiş məhsulların alışını çıxır —
  alışı bilinməyən məhsullar varsa rəqəm şişir, düzəldilməlidir; düymə adlarını birləşdirmək ("Yenilə" nəyi yeniləyir).
  Proses tabındakı qazanc xəbərdarlığı ("Qazanc yoxdur" / "Zərərlə") hələlik çatdırılma xərcini və bank komissiyasını
  saymır — "Təxmini qazanc" düzələndə ikisi eyni düsturdan istifadə etməlidir.
- [ ] **Sorğusuz birbaşa təminat.** Operator ətirin hansı anbarda neçəyə olduğunu artıq bilirsə
  (məs. telefonla dəqiqləşdirib: X ətirini yalnız Aksin satır, 100 ₼), anbara sorğu göndərmədən
  anbarı seçir, qiyməti yazır və miqdarı birbaşa həmin anbara bağlayır.
  Anbara SMS/link getmir. Tarixçədə operator, mənbə (telefon / WhatsApp / digər) və vaxt qalır.
- [ ] **Qeyri-müəyyən bank əməliyyatlarının uzlaşdırılması:** kimin ödədiyi bilinməyən köçürməni sifarişə bağlamaq.

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
- [ ] **Email abunəliyi (yeniliklərdən xəbərdar olmaq):**
  - qonaqlar saytda email yazıb abunə olur;
  - yazılan email köhnə (mövcud) müştəriyə aiddirsə, həmin müştəri avtomatik abunə sayılır;
  - qeydiyyat formasında abunəlik checkbox-u;
  - admin paneldə abunə olanların siyahısı;
  - admin paneldən abunəçilərə email bildirişlərinin göndərilməsi.

## 3. Brauzer extension-ları

- [ ] **ferrum-filler** (`extensions/ferrum-filler/README.md`): real Ferrum formasında yoxlayıb tamamlamaq.

## 4. Prod-a çıxmazdan əvvəl

- [ ] Serverdə `schedule:run` cron və queue worker (`QUEUE_CONNECTION=database`; `NotifyPriceDrop` və növbəli email-lər üçün).
  Cron olmasa `payments:check-birbank` da işləmir — bankdan qayıtmayan müştərinin ödənişi yenə "gözləyir" qalar.
- [ ] `.env`-də `ONESIGNAL_REST_API_KEY` (olmasa push getmir).
- [ ] `.env`-də `SHORT_URL=https://paf.az` (sonra `php artisan config:cache`). Olmasa ödəniş, referal və anbar linkləri əsas domenlə gedir.
- [ ] Migrasiyaları işə salmaq.
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
- Nağd kassa, bank, sahibkarla və kuryerlə hesablaşmalar ayrıdır. Pul transferi təkrar xərc sayılmır.
- Status ID-ləri başqa mənalarla əvəz edilmir.
- Məhsulun qazancı xalis satışdan hesablanır: vahid qiymət − məhsula düşən promokod payı (endirim məhsullara proporsional
  bölünür — ödəniş sətirləri və məhsul ləğvi ilə eyni paylama, `PaymentItemsBuilder::itemShares`). Məhsul ləğv ediləndə
  promokod yenidən hesablanmır. Alış satışdan baha olan anbarı seçmək qadağan deyil, amma operatordan təsdiq soruşulur.
