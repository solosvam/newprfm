# Köhnə ParfumShop — müştəri export API-si

Bu paket köhnə `solosvam/parfumshop` layihəsinin controller quruluşuna uyğun hazırlanıb. Köhnə bazada heç nə dəyişmir. Yeni sistemin import kodu ayrıca hazırlanacaq.

## Yerləşdirmə

1. `catalog/controller/api/customer_export.php` faylını köhnə saytda eyni qovluğa yerləşdirin.
   `customer-export.php` faylını da saytın kökünə, `config.php` ilə yanaşı yerləşdirin. Bu giriş faylı storefront `index.php`-ni işlətmir və JSON-a HTML qarışmasının qarşısını alır.
2. Köhnə saytın kök `config.php` faylında `<?php` daxilində, varsa bağlanan `?>` işarəsindən əvvəl əlavə edin:

```php
define('CUSTOMER_EXPORT_API_TOKEN', 'CHANGE_ME_TO_A_RANDOM_TOKEN_OF_AT_LEAST_32_CHARACTERS');
```

3. Placeholder-i ayrıca təsadüfi tokenlə dəyişin. Token yaratmaq üçün lokal terminalda:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

`random_bytes` üçün lokal PHP 7+ tələb olunur; API controller PHP 5.6+ ilə uyğundur. Tokeni yeni sistemin `.env` faylında da eyni dəyərlə saxlayacağıq. Token URL-də göndərilmir. HTTPS istifadə edin.

## Sorğu

Köhnə saytın domenini aşağıdakı `OLD_DOMAIN` yerinə yazın. `TOKEN` yerinə yaratdığınız tokeni yazın:

```bash
curl --fail-with-body \
  -H 'X-Customer-Export-Token: TOKEN' \
  'https://OLD_DOMAIN/customer-export.php?limit=200&after_id=0'
```

Nümunə cavab:

```json
{
  "version": 1,
  "customers": [
    {
      "customer_id": 125,
      "firstname": "Ad",
      "lastname": "Soyad",
      "email": "customer@example.com",
      "telephone": "994103227575",
      "sex": 1,
      "date_added": "2019-05-17 14:23:45",
      "bonus": "12.50"
    }
  ],
  "pagination": {
    "after_id": 0,
    "limit": 200,
    "count": 1,
    "snapshot_max_id": 15000,
    "last_customer_id": 125,
    "has_more": false,
    "next_after_id": null
  }
}
```

## Növbəti hissə

`has_more = true` olduqda cavabdakı `next_after_id` və ilk cavabdakı `snapshot_max_id` ilə növbəti sorğunu edin:

```text
customer-export.php?limit=200&after_id=NEXT_AFTER_ID&snapshot_max_id=FIRST_SNAPSHOT_MAX_ID
```

`has_more = false` olduqda export bitib. Eyni import boyunca ilk `snapshot_max_id` dəyişməsin: export başladıqdan sonra yaradılan müştərilər bu keçidə daxil edilmir. Bu, bazanın dondurulmuş surəti deyil; mövcud müştərinin məlumatı/bonusu sorğular arasında dəyişərsə cari dəyəri qaytarılır. Son köçürmədə köhnə saytdakı bonus dəyişikliklərini dayandırmaq və balansları tutuşdurmaq lazımdır.

## Qaydalar

- Yalnız `oc_customer` (əslində `DB_PREFIX . 'customer'`) oxunur.
- Yalnız bazada olduğu kimi `994` + 9 rəqəm formatındakı nömrələr götürülür. `+994`, boşluqlu, `0` ilə başlayan və başqa uzunluqdakı nömrələr götürülmür.
- `bonus < 1` və `NULL` bonus `"0"` qaytarılır; digərləri onluq mətn kimi saxlanılır, float-a çevrilmir.
- `sex`: 1 kişi, 2 qadın. API yeni sistemin gender kodlarına çevirmir. Naməlum dəyərləri importer hesabatda göstərməlidir.
- Bütün uyğun nömrəli müştərilər qaytarılır; köhnə `status` sahəsinə görə əlavə filtr yoxdur.
- Şifrə və başqa müştəri məlumatları göndərilmir.
- `limit`: 1–1000; standart 200. ID üzrə artan sıra ilə səhifələnir.
- Token olmadan 401, token qurulmayıbsa 503, səhv pagination üçün 422 qaytarılır.

Yeni importer `customer_id → old_customer_id`, `firstname → name`, `lastname → surname`, `telephone → mobile`, `sex 1 → gender 1`, `sex 2 → gender 0`, `bonus → bonus_balance` uyğunluğunu tətbiq etməlidir. Köçürülən hesablar `active = 1`, `password = NULL` yaradılmalıdır ki, ilk girişdə SMS təsdiqindən sonra şifrə təyin edilsin.

Köçürmə tamamlandıqdan sonra `customer-export.php`, controller və config tokenini köhnə saytdan silin.
