# Yeni sistemdə müştəri importu

## Konfiqurasiya

Yeni saytın `.env` faylında:

```dotenv
LEGACY_CUSTOMER_EXPORT_URL=https://www.parfumshop.az/index.php?route=api/customer_export
LEGACY_CUSTOMER_EXPORT_TOKEN=KOHNE_CONFIG_PHP_DEKI_EYNI_TOKEN
```

Tokeni Git-ə əlavə etməyin. Konfiqurasiya keşlənibsə `.env` dəyişikliyindən sonra `php artisan config:cache` işlədin.

## Baza və sınaq

Yalnız yeni migration-ı tətbiq etmək üçün:

```bash
php artisan migrate --path=database/migrations/2026_10_01_180000_add_old_customer_id_to_customers.php --force
php artisan parfumshop:import-customers --dry-run
```

Standart rejim də sınaqdır; bazaya yazmaq üçün `--apply` mütləq verilməlidir. Sınaq zamanı yalnız lokal hesabat faylı yazılır.

## Real import

Hesabatı yoxlayıb köhnə bonus balanslarının dəyişməsini dayandırdıqdan sonra:

```bash
php artisan parfumshop:import-customers --apply
```

- Nömrə dəqiq `994` + 9 rəqəm olmalıdır, avtomatik düzəldilmir.
- `sex = 1` kişi (`gender = 1`), `sex = 2` qadın (`gender = 0`). Digərləri hesabatda uyğunsuz sayılır.
- `bonus < 1` üçün 0. Digər məbləğlər yeni `decimal(12,2)` balansına uyğun 2 onluq yerə yuvarlaqlaşdırılır. Hesablama float olmadan qəpiklə aparılır.
- Yeni hesab: `active = 1`, `password = NULL`. İlk girişdə SMS OTP və yeni şifrə yaratma axını işləyir. Import zamanı SMS/e-mail və qeydiyyat bonusu verilmir.
- Müsbət ilkin bonus üçün `adjustment` tarixçə qeydi yaradılır. Hesab və bonus tarixçəsi eyni transaction-dadır.
- `old_customer_id` nullable və unique-dir. Əvvəl köçürülən ID yenidən yazılmır; balans, şifrə və müştəri məlumatları dəyişdirilmir.
- Mövcud və ya həmin importda əvvəl qəbul edilmiş nömrə/email konfliktləri avtomatik birləşdirilmir. Email müqayisəsi böyük-kiçik hərfə həssas deyil.
- Ad/soyad boş və ya 30 simvoldan uzun, email səhv və ya 50 simvoldan uzun olduqda qeyd hesabatda saxlanılır; məlumat kəsilmir.
- API tokeni başqa domenə yönləndirmə ilə ötürülmür. Redirect cavabı xəta sayılır.

## Hesabat və davametmə

Hər run üçün `storage/app/private/imports/customers-*.jsonl` faylı yaranır. Müştəri qeydlərində köhnə ID, nəticə, problemli sahələr və qəbul edilmiş bonus qəpiklə göstərilir. Telefon, email, ad və token hesabatda saxlanmır.

`page_complete` və `summary` qeydlərində cursor və snapshot var. Yarımçıq run-dan sonra ya əvvəldən başlayın (əvvəl yaradılanlar skip edilir), ya da son `page_complete` məlumatı ilə davam edin:

```bash
php artisan parfumshop:import-customers --apply --after-id=SON_PAGE_COMPLETE_ID --snapshot-max-id=HEMIN_SNAPSHOT
```

Uyğunsuz və konfliktli qeydləri düzəltdikdən sonra onları yenidən yoxlamaq üçün əvvəldən başladın; cursor ilə davametmə əvvəlki səhifələrdə ötürülənləri yoxlamır. Sınaq hesabatının cursor-u ilə real importu davam etdirməyin; real import 0-dan başlamalıdır.

API və ya baza xətasında əmrin exit code-u 1 olur. Konflikt/uyğunsuz qeydlər varsa digər uyğun qeydlər işlənir, amma yekun exit code yenə 1-dir. Tam problemsiz run 0 qaytarır.

`snapshot_max_id` yeni qeyd əlavə olunmasını sərhədləyir, mövcud müştərinin balansını dondurmur. Real köçürmədə köhnə bonus istifadəsini dayandırın və yekun balansları tutuşdurun.
