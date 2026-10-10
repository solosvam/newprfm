<?php

// Admin menyusu və axtarış eyni mənbədən qidalanır.
// Bölmələr gündəlik işə görə sıralanıb; hər bəndin görünməsi öz icazəsindən asılıdır (route-dakı can: ilə eyni).
// ['separator' => true] — alt menyuda ayırıcı xətt (kənarda və ya ardıcıl qalanları AdminMenuService atır).

return [
    [
        'title'    => 'Satışlar',
        'icon'     => 'cart',
        'id'       => 'salesMenu',
        'children' => [
            ['title' => 'Sifarişlər', 'route' => 'admin.orders.index', 'permission' => 'crm'],
            ['title' => 'Asan sifarişlər', 'route' => 'admin.easy-orders.index', 'permission' => 'crm'],
            ['title' => 'Kredit müraciətləri', 'route' => 'admin.credit.applications', 'permission' => 'credit.menu'],
            ['title' => 'Anbarlar', 'route' => 'admin.procurement.warehouses', 'permission' => 'crm'],
            ['title' => 'Price listlər', 'route' => 'admin.price-lists.index', 'permission' => 'crm'],
            ['separator' => true],
            ['title' => 'Statistika', 'route' => 'admin.statistics', 'permission' => 'statistics'],
        ],
    ],
    [
        'title'    => 'Müştərilər',
        'icon'     => 'user',
        'id'       => 'customersMenu',
        'children' => [
            ['title' => 'CRM', 'route' => 'admin.crm.index', 'permission' => 'crm'],
            ['title' => 'Səbətdəki mallar', 'route' => 'admin.carts.index', 'permission' => 'crm'],
            ['title' => 'Endirim gözləyənlər', 'route' => 'admin.price-alerts.index', 'permission' => 'crm'],
            ['title' => 'Dəvətlər (referal)', 'route' => 'admin.referrals.index', 'permission' => 'crm'],
        ],
    ],
    [
        'title'    => 'Kataloq',
        'icon'     => 'gift',
        'id'       => 'catalogMenu',
        'children' => [
            ['title' => 'Məhsullar', 'route' => 'admin.product.list', 'permission' => 'products.menu'],
            ['title' => 'Endirimdəki məhsullar', 'route' => 'admin.product-discounts.index', 'permission' => 'product.discount'],
            ['title' => 'Rəylər', 'route' => 'admin.product.review.list', 'permission' => 'product.reviews'],
            ['title' => 'Axtarış idarəetməsi', 'route' => 'admin.product.search-aliases.index', 'permission' => 'product.search'],
            [
                'title'    => 'Məlumat kitabçası',
                'id'       => 'catalogDictionaryMenu',
                'children' => [
                    ['title' => 'Kateqoriyalar', 'route' => 'admin.category.list', 'permission' => 'category.menu'],
                    ['title' => 'Brendlər', 'route' => 'admin.brand.list', 'permission' => 'brands.menu'],
                    ['title' => 'Ölçülər', 'route' => 'admin.size.list', 'permission' => 'size.menu'],
                    ['title' => 'Növlər', 'route' => 'admin.type.list', 'permission' => 'type.menu'],
                    ['title' => 'Notlar', 'route' => 'admin.ingredient.list', 'permission' => 'ingredient.menu'],
                ],
            ],
        ],
    ],
    [
        'title'    => 'Marketinq',
        'icon'     => 'sale-tag',
        'id'       => 'marketingMenu',
        'children' => [
            ['title' => 'Promo kodlar', 'route' => 'admin.promo-codes.index', 'permission' => 'promo.list'],
            ['title' => 'Bannerlər', 'route' => 'admin.banner.list', 'permission' => 'site.banners'],
            ['title' => 'Popup-lar', 'route' => 'admin.popups.index', 'permission' => 'site.popups'],
            ['title' => 'Vitrin (Populyar)', 'route' => 'admin.featured.index', 'permission' => 'site.featured'],
            ['title' => 'Məlumat səhifələri', 'route' => 'admin.pages.index', 'permission' => 'site.pages'],
            ['title' => 'FAQ', 'route' => 'admin.faq.list', 'permission' => 'site.faq'],
        ],
    ],
    [
        'title'      => 'Kassa',
        'icon'       => 'wallet',
        'route'      => 'admin.finance.index',
        'permission' => 'finance',
    ],
    [
        'title'    => 'Ayarlar',
        'icon'     => 'gear',
        'id'       => 'settingsMenu',
        'children' => [
            ['title' => 'Bonuslar', 'route' => 'admin.settings.bonuses', 'permission' => 'system.settings'],
            ['title' => 'Referal', 'route' => 'admin.settings.referral', 'permission' => 'system.settings'],
            ['title' => 'Sifariş və çatdırılma', 'route' => 'admin.settings.orders', 'permission' => 'system.settings'],
            ['title' => 'Banner ölçüləri', 'route' => 'admin.settings.banners', 'permission' => 'system.settings'],
            ['title' => 'Əlaqə məlumatları', 'route' => 'admin.settings.contact', 'permission' => 'system.settings'],
            [
                'title'    => 'Kredit',
                'id'       => 'creditSettingsMenu',
                'children' => [
                    ['title' => 'Faizlər', 'route' => 'admin.credit.periods', 'permission' => 'credit.menu'],
                    ['title' => 'Şərtlər və qaydalar', 'route' => 'admin.credit.terms', 'permission' => 'credit.menu'],
                ],
            ],
            ['title' => 'SMS', 'route' => 'admin.sms-template.index', 'permission' => 'system.sms'],
            [
                'title'    => 'İstifadəçilər',
                'id'       => 'usersMenu',
                'children' => [
                    ['title' => 'Əməkdaşlar', 'route' => 'admin.user.list', 'permission' => 'user.list'],
                    ['title' => 'Rollar', 'route' => 'admin.role.list', 'permission' => 'role.list'],
                    ['title' => 'İcazələr', 'route' => 'admin.permission.list', 'permission' => 'permission.list'],
                ],
            ],
        ],
    ],
    [
        'title' => 'Qısayollar',
        'icon'  => 'shortcut',
        'route' => 'admin.shortcuts',
    ],
];
