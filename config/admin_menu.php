<?php

// Admin menyusu və axtarış eyni mənbədən qidalanır.

return [
    [
        'title'      => 'Admin',
        'icon'       => 'power',
        'id'         => 'adminMenu',
        'permission' => 'admin.menu',
        'children'   => [
            [
                'title'      => 'Əməkdaşlar',
                'route'      => 'admin.user.list',
                'permission' => 'user.list',
            ],
            [
                'title'      => 'Rol və icazələr',
                'id'         => 'role_perms',
                'permission' => 'role.perm.menu',
                'children'   => [
                    [
                        'title'      => 'Rollar',
                        'route'      => 'admin.role.list',
                        'permission' => 'role.list',
                    ],
                    [
                        'title'      => 'İcazə səhifələri',
                        'route'      => 'admin.permission.list',
                        'permission' => 'permission.list',
                    ],
                ],
            ],
            [
                'title'      => 'SMS şablonları',
                'route'      => 'admin.sms-template.index',
                'permission' => 'system.sms',
            ],
            [
                'title'      => 'Promo kodlar',
                'route'      => 'admin.promo-codes.index',
                'permission' => 'promo.list',
            ],
            [
                'title'      => 'Ayarlar',
                'route'      => 'admin.settings.index',
                'permission' => 'system.settings',
            ],
            [
                'title'      => 'Kredit',
                'id'         => 'credit_menu',
                'permission' => 'credit.menu',
                'children'   => [
                    [
                        'title' => 'Müraciətlər',
                        'route' => 'admin.credit.applications',
                    ],
                    [
                        'title' => 'Faizlər',
                        'route' => 'admin.credit.periods',
                    ],
                    [
                        'title' => 'Şərtlər və qaydalar',
                        'route' => 'admin.credit.terms',
                    ],
                ],
            ],
            [
                'title'      => 'Sayt',
                'id'         => 'site-parameters',
                'permission' => 'site.menu',
                'children'   => [
                    [
                        'title'      => 'Bannerlər',
                        'route'      => 'admin.banner.list',
                        'permission' => 'site.banners',
                    ],
                    [
                        'title'      => 'FAQ',
                        'route'      => 'admin.faq.list',
                        'permission' => 'site.faq',
                    ],
                ],
            ],
        ],
    ],

    [
        'title'    => 'Məhsullar',
        'icon'     => 'gift',
        'id'       => 'whmenu',
        'children' => [
            [
                'title'      => 'Məhsullar',
                'route'      => 'admin.product.list',
                'permission' => 'products.menu',
            ],
            [
                'title'      => 'Axtarış idarəetməsi',
                'route'      => 'admin.product.search-terms.index',
                'permission' => 'product.search',
            ],
            [
                'title'      => 'Rəylər',
                'route'      => 'admin.product.review.list',
                'permission' => 'product.reviews',
            ],
            [
                'title'      => 'Kateqoriyalar',
                'route'      => 'admin.category.list',
                'permission' => 'category.menu',
            ],
            [
                'title'      => 'Brendlər',
                'route'      => 'admin.brand.list',
                'permission' => 'brands.menu',
            ],
            [
                'title'      => 'Ölçülər',
                'route'      => 'admin.size.list',
                'permission' => 'size.menu',
            ],
            [
                'title'      => 'Növlər',
                'route'      => 'admin.type.list',
                'permission' => 'type.menu',
            ],
            [
                'title'      => 'Notlar',
                'route'      => 'admin.ingredient.list',
                'permission' => 'ingredient.menu',
            ],
        ],
    ],

    [
        'title'      => 'Asan sifariş',
        'icon'       => 'cart',
        'route'      => 'admin.easy-orders.index',
        'permission' => 'crm',
    ],

    [
        'title'      => 'CRM',
        'icon'       => 'user',
        'route'      => 'admin.crm.index',
        'permission' => 'crm',
    ],

    [
        'title'      => 'Anbarlar',
        'icon'       => 'boxes',
        'route'      => 'admin.procurement.warehouses',
        'permission' => 'crm',
    ],

    [
        'title'      => 'Kassa',
        'icon'       => 'wallet',
        'route'      => 'admin.finance.index',
        'permission' => 'finance',
    ],

    [
        'title'      => 'Qısayollar',
        'icon'       => 'shortcut',
        'route'      => 'admin.shortcuts',
    ],
];
