<?php

return [
    'permissions' => [
        \Larapress\ECommerce\CRUD\ProductCRUDProvider::class,
        \Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider::class,
        \Larapress\ECommerce\CRUD\ProductTypeCRUDProvider::class,
        \Larapress\ECommerce\CRUD\BankGatewayCRUDProvider::class,
        \Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider::class,
        \Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider::class,
        \Larapress\ECommerce\CRUD\CartCRUDProvider::class,
        \Larapress\ECommerce\CRUD\FileUploadCRUDProvider::class,
        \Larapress\ECommerce\CRUD\GiftCodeCRUDProvider::class,
    ],

    'controllers' => [
        \Larapress\ECommerce\Controllers\ProductController::class,
        \Larapress\ECommerce\Controllers\ProductCategoryController::class,
        \Larapress\ECommerce\Controllers\ProductTypeController::class,
        \Larapress\ECommerce\Controllers\BankGatewayController::class,
        \Larapress\ECommerce\Controllers\BankGatewayTransactionController::class,
        \Larapress\ECommerce\Controllers\WalletTransactionController::class,
        \Larapress\ECommerce\Controllers\CartController::class,
        \Larapress\ECommerce\Controllers\FileUploadController::class,
        \Larapress\ECommerce\Controllers\GiftCodeController::class,
    ],

    'routes' => [
        'bank_gateways' => [
            'name' => 'bank-gateways'
        ],
        'bank_gateway_transactions' => [
            'name' => 'bank-gateway-transactions'
        ],
        'carts' => [
            'name' => 'carts',
        ],
        'products' => [
            'name' => 'products'
        ],
        'product_categories' => [
            'name' => 'product-categories',
        ],
        'product_types' => [
            'name' => 'product-types',
        ],
        'wallet_transactions' => [
            'name' => 'wallet-transactions',
        ],
        'gift_codes' => [
            'name' => 'gift-codes',
        ],
        'file_uploads' => [
            'name' => 'file-uploads'
        ]
    ],

    'file_upload_processors' => [
        \Larapress\ECommerce\Services\VOD\VideoFileProcessor::class,
        \Larapress\ECommerce\Services\Azmoon\AzmoonZipFileProcessor::class,
    ],

    'repository' => [
        'per_page' => 50
    ],

    'banking' => [
        'ports' => [
            'zarinpal' => Larapress\ECommerce\Services\Banking\Ports\Zarrinpal\ZarrinPalPortInterface::class
        ],

        'currency' => [
            'id' => 1,
            'title' => 'تومان'
        ],

        'redirect' => [
            'already' => '/me/carts',
            'success' => '/me/products',
            'failed' => '/me/current-cart/',

            'increase_success' => '/me/carts',
            'increase_failed' => '/me/carts',
        ],
        'default_gateway' => 1,
    ],

    'vod' => [
        'hls_variants' => [
            264 => [426, 240],
            878 => [640, 360],
            1128 => [854, 480],
            2628 => [1280, 720],
        ],
        'queue' => 'jobs'
    ],

    'lms' => [
        'course_file_upload_default_form_id' => 2,
        'support_group_default_form_id' => 4,
        'course_presense_default_form_id' => 3,
        'support_profile_form_id' => 0,
        'profile_form_id' => 1,
    ]
];
