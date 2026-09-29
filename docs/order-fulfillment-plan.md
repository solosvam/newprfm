# Sifarişin təminatı və hesablaşma planı

## Razılaşdırılmış qaydalar

- Müştəriyə ümumi mərhələlər görünür; anbar sorğuları və alış qiymətləri daxili məlumatdır.
- Bir sifariş məhsulunun miqdarı bir neçə anbara bölünə bilər.
- Anbar cavabı, operatorun seçimi, rezervasiya təsdiqi və faktiki götürmə ayrı hadisələrdir.
- Sorğuya cavab gəlməməsi “yoxdur” demək deyil. Köhnə cavablar silinmir.
- Telefon və WhatsApp cavablarını operator daxil edə bilər. Sonra anbara/sorğuya məxsus link və Telegram eyni prosesə bağlanacaq.
- Sifariş boyu bir kuryer işləyir. Kuryer ayrıca model deyil: users və admin guard üzərindən rol/icazə ilə işləyir.
- Anbara rezervasiya bildirişində seçilmiş məhsul, say və kuryer göstəriləcək.
- Hədiyyəlik bükmə seçilibsə, kuryer ünvanadək qablaşdırıb loqolu çantaya qoyur.
- Tapılmayan məhsul kuryer təyinatından əvvəl müştərinin razılığı ilə ləğv edilir; digər məhsullar çatdırılır.
- Ödəniş yaradılarkən ödənişə daxil olan məhsullar/miqdarlar/məbləğlər ayrıca cədvəldə saxlanacaq. Refund sətirləri onlara bağlanacaq.
- Ləğvdə bonus balansı uyğun məbləğdə azalır və “Sifariş ləğvi” qeydi yazılır.
- orders cədvəlinə məhsul və ünvan surəti əlavə edilməyəcək.
- Admin panel Azərbaycan dilindədir: name_az.
- Məhsulu götürmək pul ödəmək deyil. Anbar ödənişi faktiki ödəyən, mənbə, məbləğ və təminat hissəsinə bağlanır.
- Kuryerin müsbət hesablaşma qalığı şirkətə borcu, mənfi qalıq şirkətin kuryerə borcudur.
- Mərkəzin anbara birbaşa ödənişi kuryerin balansına yazılmır.
- Nağd kassa, bank, sahibkarla və kuryerlə hesablaşmalar ayrıdır. Pul transferi təkrar xərc sayılmır.

## Birinci hissə — hazırdır

Anbar idarəetməsi, sifarişdə seçilmiş məhsullar üçün bir/toplu anbar sorğusu yaratmaq,
operatorun cavab daxil etməsi və cavab tarixçəsi, son təkliflərin müqayisəsi,
miqdar üzrə seçimlər və onların ləğv tarixçəsi.

Giriş: admin menyusu → Anbarlar; CRM sifariş detalı → Anbar sorğuları və təminat.
Bütün marşrutlar auth:admin və can:crm ilə qorunur.

Yeni cədvəllər:
warehouses, warehouse_requests, warehouse_request_items, warehouse_offers,
order_item_allocations, allocation_status_logs.

Bu hissə ümumi sifariş statusunu, satış məbləğini və ödənişləri dəyişmir.
Sorğu yaratmaq mesaj göndərmir. Seçim rezervasiya deyil.
selected/cancelled hazırkı təminat seçiminin daxili vəziyyətləridir; kuryer mərhələləri hələ yoxdur.

Yoxlama:
`vendor/bin/phpunit tests/Feature/Procurement --do-not-cache-result`
Testlər müstəqil SQLite yaddaş bazasında işləyir. Lokal MySQL testlərdə dəyişdirilmir.
Miqdarların rəqabətli dəyişmələri eyni sifariş sətrinə tranzaksiya kilidi ilə ardıcıllaşdırılır;
SQLite testləri real MySQL paralel kilidlənməsini yoxlamır.

## Növbəti hissələr

1. Ümumi və məhsul statusları, genişləndirilmiş status tarixçəsi, müştəri görünüşü.
2. Ödəniş məhsulları, məhsul/miqdar üzrə ləğv, endirim və bonus bölgüsü, refund əlaqəsi.
3. Pul əməliyyatları reyestri, kassa/bank, kuryer və sahibkar hesablaşmaları, anbar borcları.
4. User rolları ilə kuryer tapşırıqları, götürmə, ödəniş, qablaşdırma və çatdırılma.
5. Anbar cavab linkləri, Telegram, rezervasiya təsdiqi və bildiriş tarixçəsi.
6. Müştəri SMS-ləri və qeyri-müəyyən bank əməliyyatlarının uzlaşdırılması.

Status ID-ləri başqa mənalarla əvəz edilməyəcək. Mövcud kodlar üzrə uyğunlaşdırma ediləcək.
Ödənilmiş sifarişdə məhsul ləğvi refund və bonus qaydaları hazır olandan sonra aktivləşdiriləcək.
