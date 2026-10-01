# Köhnə server daxilində köçürmə

`migrate-local.php` faylını köhnə saytın `config.php` faylı ilə eyni qovluğa yerləşdirin. Dörd yeni cədvəl əvvəlcədən yaradılmalıdır. Skript yalnız çatışmayan `checked` sütunlarını köhnə customer və credits2 cədvəllərinə əlavə edir.

Brauzerdə `/migrate-local.php` açın, config.php-dəki `CUSTOMER_EXPORT_API_TOKEN` dəyərini giriş formasına yazın. Token URL-də yazılmır. Girişdən sonra hər refresh maksimum 200 müştəri qeydi və 200 kredit müştərisi işləyir; avtomatik refresh yoxdur. PHP PDO MySQL, mbstring və OpenSSL tələb olunur.

Müştərilər dəqiq 994 və ardınca 9 rəqəm olan telefonla seçilir. Ad/soyad 30, email 50 simvol həddi, email formatı, sex 1/2, date_added tarixi yoxlanır. Eyni telefon/email konfliktləri birləşdirilmir. Bonus <1 sıfır olur; >=1 olduqda yeni müştərinin balansı və adjustment bonus tarixçəsi birlikdə yazılır. Köhnə ID old_customer_id-də saxlanır, yeni parol NULL qalır. Mövcud old_customer_id təkrar customer/bonus yaratmır.

Kreditlər oc_customer.checked=1 və customers.old_customer_id uyğunluğu ilə seçilir. Həmin köhnə customer_id üçün BÜTÜN kredit qeydləri qiymətləndirilir: fathername, gender, card_id, card_fin, iki qohumun nömrə/adları, job_type, job_salary, id_front, id_back. Hər boş olmayan sahə 1 baldır. Ən çox ballı qeyd bütöv götürülür; bərabərlikdə kiçik id seçilir. Fərqli FIN-lər konflikt deyil. Sahələr başqa qeydlərdən qarışdırılmır. Vəsiqə seriya/nömrəsi və FIN normallaşdırılır. Kredit qeydindəki male/female yeni customers.gender sahəsini yeniləyir.

Mövcud profil yenidən yazılmır. Uğurlu profil köçürməsi və ya artıq mövcud profil olduqda həmin köhnə customer_id-yə aid bütün kreditlər checked=1 edilir. Şəkillər credit_images-dən frontend/uploads/customers qovluğuna kopyalanır; yeni fayl adları profildə saxlanır. Boş şəkil sahəsi və ya tapılmayan fayl NULL qalır; profilin köçürülməsi davam edir. JPG/JPEG/PNG/WEBP/PDF dəstəklənir. Yeni serverə keçərkən frontend/uploads/customers qovluğunu da köçürmək lazımdır.

Session daxilində cursor konflikt/uyğunsuz qeydlərin hər refreshdə önə çıxmasını əngəlləyir. Tur bitəndə cursor sıfırlanır və köçürülməyən qeydlər növbəti turda yenidən yoxlanır. Xətalı qeydlər checked=1 edilmir. Başqa brauzer sessiyasında cursor sıfırdan başlayır, amma köçürülənlər təkrarlanmır. GET_LOCK eyni vaxtda iki skriptin işləməsini əngəlləyir; bu qovluqdakı CLI skripti ilə eyni lock istifadə edilir.

Yeni cədvəllərdə InnoDB tranzaksiyası istifadə edilir. Köhnə cədvəllər MyISAM-dırsa onların checked dəyişiklikləri tranzaksiyaya daxil deyil. Mövcud old_customer_id/profil yoxlamaları tamamlanmış insertlərin təkrarını əngəlləyir. Köçürmə bitdikdən sonra migrate-local.php-ni serverdən silin.
