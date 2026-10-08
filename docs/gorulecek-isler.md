# parfumshop — görüləcək işlər

Layihə üzrə **yeganə** görüləcək işlər sənədi. Yeni iş əlavə olunanda, iş bitəndə və ya qərar veriləndə yalnız bu fayl yenilənir.
Əvvəlki `docs/order-fulfillment-plan.md` və `docs/backlog.md` bu fayla birləşdirilib silinib.

Son yenilənmə: 8 oktyabr 2026.

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
- [ ] **Referal linki `paf.az` üzərindən olmalıdır.** Müştərinin kabinetdə (`/profile/referral`) kopyalayıb paylaşdığı dəvət linki
  hazırda saytın öz domenindədir (`/r/{code}`, `ReferralService::linkFor`). Link `paf.az/...` formasında olmalı,
  açılanda indiki `referral.track` məntiqi ilə işləməlidir (kod cookie-yə yazılır, qeydiyyata yönləndirilir).
  Influencer proqramındakı `paf.az` qısa link sistemi ilə ortaq qurulmalıdır (bölmə 6).

## 3. Brauzer extension-ları

- [ ] **ferrum-filler** (`extensions/ferrum-filler/README.md`): real Ferrum formasında yoxlayıb tamamlamaq.

## 4. Prod-a çıxmazdan əvvəl

- [ ] **Birbank ödənişlərini yoxlayan job.** Müştəri callback-ə qayıtmasa, ödəniş həmişəlik "gözləyir" qalır
  (`BirbankPaymentController::callback` yeganə yoxlamadır), `hasPendingPayment()` ləğv/təkrar ödənişi bloklayır.
  Job `Birbank::verify()` ilə gözləyən ödənişləri yoxlamalı və callback-dəki eyni yeniləməni etməlidir (ortaq servisə çıxarmaq).
  Repoda Birbank status sənədləri yoxdur (yalnız FullyPaid / Cancelled / Declined / Refused).
- [ ] **SMS ödəniş linkinə son istifadə müddəti** (`orders.pay_token`, `OrderPayLinkService` — hazırda müddətsizdir).
- [ ] Serverdə `schedule:run` cron və queue worker (`QUEUE_CONNECTION=database`; `NotifyPriceDrop` və növbəli email-lər üçün).
- [ ] `.env`-də `ONESIGNAL_REST_API_KEY` (olmasa push getmir).
- [ ] Migrasiyaları işə salmaq.
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
- [ ] Live-a keçid: tətbiqi düzgün Business portfolio-ya köçürmək, Business Verification, App Review.

## 7. Git

- [ ] Köhnə `fix/banner-product-cls` branch-ı (28 sentyabr, 5 commit, main-ə birləşdirilməyib). Banner və məhsul şəkillərinin ölçüləri artıq main-də başqa yolla yazılıb, ona görə branch-a ehtiyac görünmür — yoxlayıb silmək olar.

---

## Qüvvədə olan qərarlar

- Referal endirimi yalnız sayt sifarişlərinə şamil olunur, CRM sifarişlərinə yox.
- Bonus balansı ayrıca, **tam** ödəniş üsuludur. Qismən bonusla ödəniş olmayacaq.
- Bonus yalnız sifariş təhvil veriləndə qazanılır (bonus balansı və öz kreditimizlə ödənişlərdən başqa). Sifarişin bonus faizi yaradılarkən sabitlənir.
- Köhnə müştərilərin (~12 min) bonusları hələlik qalır. Sıfırlanması barədə qərar sonra veriləcək — o vaxta qədər heç nə edilmir.
- Müştəri ehtiyat nömrəsini (`mobile_2`) saytda görmür və dəyişmir.
- Öz kreditimizlə (hissə-hissə) alışda məhsul endirimi tətbiq olunmur. Birbank taksitində tətbiq olunur. Birbank taksiti "hissə-hissə" sayılmır.
- Promo kod endirimli məhsullara tətbiq olunmur.
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
