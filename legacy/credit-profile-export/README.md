# Köhnə kredit profillərinin köçürülməsi

## Köhnə sayta yerləşdirmə

Bu iki faylı yerləşdirin:

1. `credit-profile-export.php` — saytın kökünə, `config.php` ilə yanaşı.
2. `catalog/controller/api/credit_profile_export.php` — eyni controller yoluna.

Əvvəl customer export üçün qurulmuş `CUSTOMER_EXPORT_API_TOKEN` istifadə olunur; yeni token lazım deyil. PHP 5.6+ ilə uyğundur. Export yalnız oxuyur, köhnə bazanı dəyişmir.

API: `https://www.parfumshop.az/credit-profile-export.php?limit=50`

Header: `X-Customer-Export-Token: mövcud-token`

Mənbə: `DB_PREFIX . 'credits2'`. Cədvəlin ID sütunu `id` qəbul edilir (köhnə repo ilə uyğun).

Yalnız bazada olduğu kimi `^994[0-9]{9}$` formatındakı nömrələr qaytarılır. `+994`, `050`, boşluqlu və başqa uzunluqlu nömrələr düzəldilmir və götürülmür.

Eyni nömrənin bütün qeydləri bir qrupda qaytarılır. Səhifələmə `after_mobile` üzrədir, qeydlər qrup daxilində `id DESC` sırası ilədir. `snapshot_max_id` ilk sorğuda təyin olunur və sonrakı sorğularda eyni saxlanılır. Yeni ID-lər bu run-a daxil edilmir, mövcud qeyd dəyişiklikləri isə dondurulmur.

## Yeni sistemdə əmrlər

Standart URL artıq konfiqurasiyadadır. Başqa domen olduqda yeni `.env`-də dəyişin:

```dotenv
LEGACY_CREDIT_EXPORT_URL=https://www.parfumshop.az/credit-profile-export.php
```

Mövcud `LEGACY_CUSTOMER_EXPORT_TOKEN` istifadə olunur. Konfiqurasiya keşlənibsə `php artisan config:cache` işlədin.

Əvvəl yoxlama:

```bash
php artisan parfumshop:import-credit-profiles --dry-run
```

Hesabatdan sonra real köçürmə:

```bash
php artisan parfumshop:import-credit-profiles --apply
```

Standart rejim sınaqdır. Sınaq bazanı və şəkil qovluğunu dəyişmir; şəkilləri yaddaşa yükləyib formatını yoxlayır, lokal hesabat yazır. Bu, bir neçə sorğu tələb etdiyinə görə adi customer importundan daha yavaş ola bilər. `--batch=10` ilə kiçik səhifələr seçilə bilər.

## Tamamlama qaydaları

- Müştəri dəqiq mobile ilə tapılır. Tapılmırsa yaradılmır. Nömrə yeni sistemdə bir neçə müştəriyə aiddirsə konflikt sayılır.
- Mövcud `isComplete()` qaydasına görə profil tamdırsa tam ötürülür. AA profilində arxa üz məcburi deyil; belə tam profilə də toxunulmur.
- Natamam profildə yalnız `NULL`, boş mətn və boşluqlardan ibarət sahələr tamamlanır. `0` boş sayılmır.
- Eyni nömrədə müxtəlif FIN, mövcud profillə fərqli FIN, başqa müştəridə eyni FIN və müxtəlif vəsiqələr konflikt sayılır. Bu halda heç nə dəyişdirilmir, şəkil yüklənmir.
- Etibarlı FIN olmadan şəxsiyyət məlumatı avtomatik birləşdirilmir. FIN-i boş olan köhnə qeydlər başqa qeydin məlumatını tamamlamaq üçün istifadə olunmur.
- Eyni FIN/vəsiqəyə uyğun qeydlərdə ən yeni uyğun məlumat götürülür; boş və ya uyğunsuz sahə üçün əvvəlki uyğun qeydlərə baxılır.
- `card_id` böyük hərfə çevrilir; boşluq, `№`, `#` və tire təmizlənir. `AZE 09217804 → AZE / 09217804`, `aa1559649 → AA / 1559649`. Sıfırlar saxlanılır. Seriya və nömrə birlikdə yoxlanılır; mövcud hissə fərqlidirsə konflikt olur.
- `card_fin → fin`, `fathername → father_name`, `job_type → workplace_name`, `job_salary → salary`; qohum nömrələri/adları müvafiq `relative_*` sahələrinə yazılır. FIN 7 alfanumerik simvol, maaş müsbət və ən çox 2 onluq yer olmalıdır.
- Müştərinin mövcud ad/soyadı, nömrəsi, şifrəsi, bonusu və yaranma tarixi dəyişdirilmir. Gender yalnız boşdursa `male → 1`, `female → 0` ilə tamamlanır; mövcud `0`/`1` saxlanılır.
- Natamam profildə çatışmayan ön və arxa şəkil eyni FIN/vəsiqəli mənbədən gətirilir; AA üçün də köhnə arxa şəkil varsa gətirilir. Mövcud şəkillər əvəzlənmir.
- Şəkillər `https://www.parfumshop.az/credit_images/` ünvanından yüklənərək yeni sistemin `public/frontend/uploads/customers/` qovluğunda UUID adla saxlanılır. Bütün linklər yeni lokal fayla bağlanır.
- JPEG, PNG və WEBP, ən çox 5 MB qəbul olunur. PDF, HTML cavabı, başqa formatlar, yüklənməyən və həddən böyük fayllar problem kimi hesabatda qalır. Faylın həqiqi şəkil formatı yoxlanılır. Redirect izlənmir.
- Şəkil alınmasa digər uyğun boş sahələr yenə tamamlana bilər; uğursuz şəkil boş qalır, hesabatda görünür. Təkrar run çatışmayanları tamamlayır. Profil bazaya yazılmasa həmin run-da yaradılmış şəkillər təmizlənir.
- Import zamanı profil başqa operator tərəfindən dəyişərsə həmin profil üzrə yazı geri qaytarılır və xəta kimi göstərilir.

## Hesabat, dayandırma və davametmə

Hesabat: `storage/app/private/imports/credit-profiles-*.jsonl`.

Profil qeydlərində yalnız köhnə kredit ID-ləri, yeni customer ID, dəyişəcək sahə adları və problemlər saxlanılır. Ad, FIN, vəsiqə nömrəsi və şəkil məzmunu loga yazılmır. Davametmə üçün checkpoint qeydlərində telefon cursor-u saxlanılır; hesabat qovluğu private-dir.

Ctrl+C cari əməliyyatdan sonra dayandırır və ümumi import kilidini açır. Customer və kredit importu eyni vaxtda işləmir. Əvvəldən təkrar başlatmaq olar: mövcud sahə və şəkillər yenidən yazılmır.

Son checkpoint ilə davam etmək üçün:

```bash
php artisan parfumshop:import-credit-profiles --apply --after-mobile=994XXXXXXXXX --snapshot-max-id=SNAPSHOT
```

Konflikt və uğursuz şəkilləri yenidən yoxlamaq üçün əvvəldən başlayın. Sınağın cursor-u ilə real importu davam etdirməyin.

Problemli/natamam qeydlər olsa uyğun qeydlər işlənir, amma yekun exit code 1-dir. Problemsiz run 0 qaytarır. Real köçürmə başladıqdan sonra köhnə məlumatların dəyişməsini dayandırmaq tövsiyə olunur.

Köçürmə yoxlanıb bitəndən sonra köhnə serverdən bu export giriş faylını və controller-i silin.
