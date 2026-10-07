<?php

/*
 * Məlumat səhifələrinin ilkin mətnləri (migration 2026_10_08_130000 bazaya yazır; sonra admindən redaktə olunur).
 * Mənbə: köhnə saytın (www.parfumshop.az) "Haqqımızda", "Qaydalar", "Sifariş və çatdırılma" səhifələri, yeni qaydalara uyğunlaşdırılıb.
 * Çatdırılma haqqı və ödəniş üsulları mətndə yazılmır — səhifədə ayarlardan avtomatik göstərilir.
 */

return [
    'delivery' => [
        'title' => ['az' => 'Çatdırılma və ödəniş', 'en' => 'Delivery and payment', 'ru' => 'Доставка и оплата'],
        'meta' => [
            'az' => 'Parfumshop.az-da sifariş, çatdırılma müddəti və ödəniş üsulları: qapıda ödəniş, onlayn kart, Birbank taksit və hissə-hissə ödəniş.',
            'en' => 'Ordering, delivery times and payment methods at Parfumshop.az: pay on delivery, online card payment, Birbank installments and installment plans.',
            'ru' => 'Заказ, сроки доставки и способы оплаты на Parfumshop.az: оплата при доставке, онлайн-оплата картой, рассрочка Birbank и оплата частями.',
        ],
        'body' => [
            'az' => '<h2>Sifariş necə verilir?</h2><p>Bəyəndiyiniz ətri səbətə əlavə edin və sifarişi rəsmiləşdirin. Operatorumuz iş saatlarında sizinlə əlaqə saxlayıb sifarişi təsdiqləyəcək və çatdırılma üçün sizə uyğun vaxtı dəqiqləşdirəcək.</p><h2>Çatdırılma</h2><ul><li>Bakı daxilində çatdırılma ünvandan asılı olaraq adətən 30 dəqiqədən 2 saata qədər çəkir.</li><li>Seçilmiş məhsul anbarda olmadıqda çatdırılma tarixi dəyişə bilər — belə halda operatorumuz sizə xəbər verəcək və alternativ təklif edəcək.</li><li>Sifarişi qəbul edərkən məhsulu kuryerin yanında diqqətlə yoxlayın.</li></ul><h2>İş saatları</h2><p>Həftədə 6 gün, 09:00-dan 19:00-dək. Bazar günü istirahət günüdür.</p>',
            'en' => '<h2>How to order</h2><p>Add the fragrance you like to your cart and complete checkout. Our operator will contact you during working hours to confirm the order and agree on a convenient delivery time.</p><h2>Delivery</h2><ul><li>Delivery within Baku usually takes from 30 minutes to 2 hours depending on the address.</li><li>If the selected product is out of stock, the delivery date may change — our operator will let you know and offer alternatives.</li><li>Please check the product carefully in the presence of the courier.</li></ul><h2>Working hours</h2><p>6 days a week, 09:00–19:00. Sunday is a day off.</p>',
            'ru' => '<h2>Как оформить заказ</h2><p>Добавьте понравившийся аромат в корзину и оформите заказ. Наш оператор свяжется с вами в рабочее время, подтвердит заказ и согласует удобное время доставки.</p><h2>Доставка</h2><ul><li>Доставка по Баку обычно занимает от 30 минут до 2 часов в зависимости от адреса.</li><li>Если выбранного товара нет на складе, дата доставки может измениться — оператор сообщит вам и предложит альтернативу.</li><li>При получении внимательно проверьте товар в присутствии курьера.</li></ul><h2>Часы работы</h2><p>6 дней в неделю, с 09:00 до 19:00. Воскресенье — выходной.</p>',
        ],
    ],
    'returns' => [
        'title' => ['az' => 'Qaytarma və dəyişdirmə', 'en' => 'Returns and exchanges', 'ru' => 'Возврат и обмен'],
        'meta' => [
            'az' => 'Parfumshop.az-da məhsulun qaytarılması və dəyişdirilməsi qaydaları.',
            'en' => 'Return and exchange rules at Parfumshop.az.',
            'ru' => 'Правила возврата и обмена товаров на Parfumshop.az.',
        ],
        'body' => [
            'az' => '<h2>Çatdırılma zamanı yoxlama</h2><p>Sifarişi qəbul edərkən məhsulu kuryerin yanında diqqətlə nəzərdən keçirin. Görünüşü sizi qane etmirsə və ya sifarişə uyğun deyilsə, <strong>qablaşdırmanı açmadan</strong> məhsulu kuryerə qaytarın — biz onu mütləq dəyişəcəyik. Görünüşlə bağlı narazılıq yalnız çatdırılma zamanı qəbul olunur.</p><h2>Qüsurlu məhsul</h2><p>Qəbul etdiyiniz məhsulda qüsur və ya zədə aşkarlasanız, onu yeni məhsulla dəyişəcəyik.</p><h2>Pulun geri qaytarılması</h2><p>Pulun geri qaytarılması üçün qablaşdırma qorunmalı və ətirdən istifadə edilməməlidir. İstifadə olunmaması şüşənin içindəki borucuqda hava axınının olması ilə müəyyən edilir; buna görə kuryer ödəniş alınmadan ətrin sınanmasına icazə verə bilmir. Onlayn kartla ödənilmiş məbləğ eyni karta qaytarılır.</p><h2>Selofan örtük haqqında</h2><p>Qablaşdırmada selofan örtüyün olmaması məhsulun orijinal olmaması demək deyil — bir çox istehsalçı məhsulunu örtüksüz təqdim edir.</p><p>Təklif və iradlarınızı <a href="mailto:info@parfumshop.az">info@parfumshop.az</a> ünvanına yaza bilərsiniz.</p>',
            'en' => '<h2>Inspection on delivery</h2><p>Please inspect the product carefully in the presence of the courier. If its appearance does not satisfy you or it does not match your order, return it to the courier <strong>without opening the packaging</strong> — we will replace it. Complaints about appearance are accepted only at delivery.</p><h2>Defective products</h2><p>If you find a defect or damage, we will replace the product with a new one.</p><h2>Refunds</h2><p>For a refund, the packaging must be intact and the fragrance unused. Non-use is determined by air flow in the tube inside the bottle, which is why the courier cannot allow testing before payment. Online card payments are refunded to the same card.</p><h2>About cellophane wrapping</h2><p>The absence of cellophane wrapping does not mean the product is not original — many manufacturers ship their products unwrapped.</p><p>Send suggestions and complaints to <a href="mailto:info@parfumshop.az">info@parfumshop.az</a>.</p>',
            'ru' => '<h2>Проверка при доставке</h2><p>Внимательно осмотрите товар в присутствии курьера. Если внешний вид вас не устраивает или товар не соответствует заказу, верните его курьеру, <strong>не вскрывая упаковку</strong>, — мы обязательно его заменим. Претензии по внешнему виду принимаются только при доставке.</p><h2>Товар с дефектом</h2><p>Если вы обнаружили дефект или повреждение, мы заменим товар на новый.</p><h2>Возврат денег</h2><p>Для возврата денег упаковка должна быть сохранена, а аромат не использован. Это определяется по наличию воздуха в трубке внутри флакона, поэтому курьер не может разрешить тестирование до оплаты. Онлайн-оплата картой возвращается на ту же карту.</p><h2>О целлофановой упаковке</h2><p>Отсутствие целлофана не означает, что товар неоригинальный — многие производители выпускают продукцию без него.</p><p>Предложения и претензии присылайте на <a href="mailto:info@parfumshop.az">info@parfumshop.az</a>.</p>',
        ],
    ],
    'about' => [
        'title' => ['az' => 'Haqqımızda', 'en' => 'About us', 'ru' => 'О нас'],
        'meta' => [
            'az' => 'Parfumshop.az — 2000-ci ildən fəaliyyət göstərən IMAGE mağazasının onlayn ətir mağazası.',
            'en' => 'Parfumshop.az — the online perfume store of IMAGE, operating since 2000.',
            'ru' => 'Parfumshop.az — интернет-магазин парфюмерии магазина IMAGE, работающего с 2000 года.',
        ],
        'body' => [
            'az' => '<p>Parfumshop.az saytı 2000-ci ildən fəaliyyət göstərən <strong>IMAGE</strong> mağazası tərəfindən yaradılıb. Məqsədimiz istədiyiniz ətri vaxt itirmədən, olduğunuz yerdən rahatlıqla sifariş edə bilməyinizdir.</p><p>Sizə münasib qiymətlərlə yüksək keyfiyyətli, orijinal ətirlər təqdim edirik. Artıq minlərlə müştərinin inamını qazanmışıq və sizi də müştərilərimiz arasında görməkdən məmnun olarıq.</p><p><em>Parfumshop.az — həyatınızın ən ətirli səhifəsi.</em></p>',
            'en' => '<p>Parfumshop.az was created by <strong>IMAGE</strong>, a store operating since 2000. Our goal is to let you order the fragrance you want quickly and comfortably, wherever you are.</p><p>We offer high-quality, original fragrances at fair prices. Thousands of customers already trust us, and we would be glad to welcome you too.</p><p><em>Parfumshop.az — the most fragrant page of your life.</em></p>',
            'ru' => '<p>Сайт Parfumshop.az создан магазином <strong>IMAGE</strong>, который работает с 2000 года. Наша цель — чтобы вы могли быстро и удобно заказать нужный аромат, где бы вы ни находились.</p><p>Мы предлагаем качественные оригинальные ароматы по доступным ценам. Нам уже доверяют тысячи покупателей, и мы будем рады видеть среди них и вас.</p><p><em>Parfumshop.az — самая ароматная страница вашей жизни.</em></p>',
        ],
    ],
    'contact' => [
        'title' => ['az' => 'Əlaqə', 'en' => 'Contact', 'ru' => 'Контакты'],
        'meta' => [
            'az' => 'Parfumshop.az ilə əlaqə: telefon, WhatsApp, e-poçt və iş saatları.',
            'en' => 'Contact Parfumshop.az: phone, WhatsApp, email and working hours.',
            'ru' => 'Контакты Parfumshop.az: телефон, WhatsApp, e-mail и часы работы.',
        ],
        'body' => [
            'az' => '<p>Sualınız, təklifiniz və ya sifarişlə bağlı müraciətiniz varsa, bizimlə istənilən rahat yolla əlaqə saxlayın — operatorlarımız iş saatlarında cavab verir.</p>',
            'en' => '<p>If you have a question, suggestion or an enquiry about an order, contact us in any convenient way — our operators reply during working hours.</p>',
            'ru' => '<p>Если у вас есть вопрос, предложение или обращение по заказу, свяжитесь с нами любым удобным способом — операторы отвечают в рабочее время.</p>',
        ],
    ],
    'terms' => [
        'title' => ['az' => 'İstifadə şərtləri', 'en' => 'Terms of use', 'ru' => 'Условия использования'],
        'meta' => [
            'az' => 'Parfumshop.az saytının istifadə şərtləri.',
            'en' => 'Terms of use of the Parfumshop.az website.',
            'ru' => 'Условия использования сайта Parfumshop.az.',
        ],
        'body' => [
            'az' => '<p>Bu şərtlər Parfumshop.az saytından (VÖEN 1400642722) istifadə və sifariş qaydalarını müəyyən edir. Saytda qeydiyyatdan keçməklə və ya sifariş verməklə bu şərtlərlə razılaşmış olursunuz.</p><h2>1. Hesab</h2><p>Qeydiyyat mobil nömrə ilə aparılır. Hesabınızın təhlükəsizliyinə (şifrə, təsdiq kodları) siz cavabdehsiniz.</p><h2>2. Qiymətlər və sifariş</h2><p>Saytdakı qiymətlərə bütün vergilər daxildir. Sifariş operator tərəfindən təsdiqləndikdən sonra qüvvəyə minir. Məhsul anbarda olmadıqda sifariş dəyişdirilə və ya ləğv edilə bilər — bu barədə sizə xəbər veriləcək.</p><h2>3. Ödəniş və çatdırılma</h2><p>Ödəniş üsulları və çatdırılma şərtləri "Çatdırılma və ödəniş" səhifəsində göstərilib.</p><h2>4. Bonuslar və promo kodlar</h2><p>Bonuslar sifariş təhvil verildikdən sonra hesabınıza yazılır və müəyyən müddət ərzində istifadə olunmalıdır. Bonus və promo kodlar nağd pula çevrilmir və başqa hesaba köçürülmür.</p><h2>5. Qaytarma</h2><p>Qaytarma və dəyişdirmə "Qaytarma və dəyişdirmə" səhifəsindəki qaydalarla həyata keçirilir.</p><h2>6. Dəyişikliklər</h2><p>Bu şərtlər yenilənə bilər; yeni versiya saytda dərc edildiyi andan qüvvədədir.</p>',
            'en' => '<p>These terms govern the use of the Parfumshop.az website (TIN 1400642722) and ordering. By registering or placing an order you agree to them.</p><h2>1. Account</h2><p>Registration uses your mobile number. You are responsible for the security of your account (password, verification codes).</p><h2>2. Prices and orders</h2><p>Prices on the site include all taxes. An order takes effect after confirmation by our operator. If a product is out of stock, the order may be changed or cancelled — you will be informed.</p><h2>3. Payment and delivery</h2><p>Payment methods and delivery terms are described on the "Delivery and payment" page.</p><h2>4. Bonuses and promo codes</h2><p>Bonuses are credited after the order is delivered and must be used within a set period. Bonuses and promo codes cannot be exchanged for cash or transferred to another account.</p><h2>5. Returns</h2><p>Returns and exchanges follow the rules on the "Returns and exchanges" page.</p><h2>6. Changes</h2><p>These terms may be updated; the new version applies from its publication on the site.</p>',
            'ru' => '<p>Настоящие условия регулируют использование сайта Parfumshop.az (VÖEN 1400642722) и оформление заказов. Регистрируясь или оформляя заказ, вы соглашаетесь с ними.</p><h2>1. Аккаунт</h2><p>Регистрация проводится по номеру мобильного телефона. Вы отвечаете за безопасность своего аккаунта (пароль, коды подтверждения).</p><h2>2. Цены и заказ</h2><p>Цены на сайте включают все налоги. Заказ вступает в силу после подтверждения оператором. Если товара нет на складе, заказ может быть изменён или отменён — мы вас уведомим.</p><h2>3. Оплата и доставка</h2><p>Способы оплаты и условия доставки указаны на странице «Доставка и оплата».</p><h2>4. Бонусы и промокоды</h2><p>Бонусы начисляются после доставки заказа и должны быть использованы в установленный срок. Бонусы и промокоды не обмениваются на деньги и не передаются на другой аккаунт.</p><h2>5. Возврат</h2><p>Возврат и обмен осуществляются по правилам страницы «Возврат и обмен».</p><h2>6. Изменения</h2><p>Условия могут обновляться; новая редакция действует с момента публикации на сайте.</p>',
        ],
    ],
    'privacy' => [
        'title' => ['az' => 'Məxfilik siyasəti', 'en' => 'Privacy policy', 'ru' => 'Политика конфиденциальности'],
        'meta' => [
            'az' => 'Parfumshop.az-da şəxsi məlumatların toplanması, istifadəsi və qorunması.',
            'en' => 'How Parfumshop.az collects, uses and protects personal data.',
            'ru' => 'Как Parfumshop.az собирает, использует и защищает персональные данные.',
        ],
        'body' => [
            'az' => '<h2>Hansı məlumatları toplayırıq</h2><ul><li>Ad, soyad, mobil nömrə və çatdırılma ünvanları;</li><li>sifariş tarixçəsi, seçilmişlər, səbət və bonus hərəkətləri;</li><li>hissə-hissə ödəniş üçün müraciətdə — şəxsiyyət vəsiqəsi məlumatları və şəkilləri;</li><li>saytın işləməsi üçün zəruri cookie-lər və brauzer yaddaşı.</li></ul><h2>Məlumatlardan necə istifadə edirik</h2><p>Sifarişin rəsmiləşdirilməsi və çatdırılması, sizinlə əlaqə (zəng, SMS, push bildirişi), bonus və kampaniyalar, hissə-hissə ödəniş müraciətinin baxılması üçün.</p><h2>Üçüncü tərəflər</h2><p>Məlumatlar yalnız xidmətin göstərilməsi üçün zəruri olduqda ötürülür: kuryer xidməti, ödəniş təşkilatları (bank), hissə-hissə ödəniş üzrə tərəfdaş kredit təşkilatı, SMS və push xidmətləri. Məlumatlarınızı satmırıq.</p><h2>Qorunma</h2><p>Şəxsiyyət vəsiqəsi şəkilləri qapalı yaddaşda saxlanılır və yalnız səlahiyyətli əməkdaşlar tərəfindən, qeydə alınmaqla görülə bilər.</p><h2>Hüquqlarınız</h2><p>Məlumatlarınıza baxmaq, düzəltmək və ya silinməsini istəmək üçün <a href="mailto:info@parfumshop.az">info@parfumshop.az</a> ünvanına yazın.</p>',
            'en' => '<h2>What we collect</h2><ul><li>Name, surname, mobile number and delivery addresses;</li><li>order history, favourites, cart and bonus activity;</li><li>for installment applications — ID card details and photos;</li><li>cookies and browser storage required for the site to work.</li></ul><h2>How we use it</h2><p>To process and deliver orders, contact you (calls, SMS, push notifications), run bonuses and campaigns, and review installment applications.</p><h2>Third parties</h2><p>Data is shared only when needed to provide the service: courier service, payment providers (banks), our installment credit partner, SMS and push services. We do not sell your data.</p><h2>Protection</h2><p>ID card images are kept in private storage and can be viewed only by authorised staff, with every access logged.</p><h2>Your rights</h2><p>To view, correct or delete your data, write to <a href="mailto:info@parfumshop.az">info@parfumshop.az</a>.</p>',
            'ru' => '<h2>Какие данные мы собираем</h2><ul><li>Имя, фамилия, номер мобильного телефона и адреса доставки;</li><li>история заказов, избранное, корзина и бонусные операции;</li><li>при заявке на оплату частями — данные и фото удостоверения личности;</li><li>cookie и данные браузера, необходимые для работы сайта.</li></ul><h2>Как мы их используем</h2><p>Для оформления и доставки заказов, связи с вами (звонки, SMS, push-уведомления), бонусов и акций, рассмотрения заявок на оплату частями.</p><h2>Третьи лица</h2><p>Данные передаются только при необходимости для оказания услуги: курьерской службе, платёжным организациям (банкам), партнёрской кредитной организации, SMS- и push-сервисам. Мы не продаём ваши данные.</p><h2>Защита</h2><p>Фото удостоверений хранятся в закрытом хранилище и доступны только уполномоченным сотрудникам с регистрацией каждого просмотра.</p><h2>Ваши права</h2><p>Чтобы просмотреть, исправить или удалить свои данные, напишите на <a href="mailto:info@parfumshop.az">info@parfumshop.az</a>.</p>',
        ],
    ],
];
