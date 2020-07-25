<?php

use Larapress\ECommerce\Controllers\BankGatewayController;
use Larapress\ECommerce\Controllers\BankGatewayTransactionController;
use Larapress\ECommerce\Controllers\CartController;
use Larapress\ECommerce\Controllers\ProductCategoryController;
use Larapress\ECommerce\Controllers\ProductController;
use Larapress\ECommerce\Controllers\ProductTypeController;
use Larapress\ECommerce\Controllers\WalletTransactionController;
use Larapress\ECommerce\Controllers\FileUploadController;
use Larapress\ECommerce\CRUD\BankGatewayCRUDProvider;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\CRUD\FileUploadCRUDProvider;
use Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\CRUD\ProductTypeCRUDProvider;
use Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider;
use Larapress\ECommerce\Services\VOD\VideoFileProcessor;

return [
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

    'permissions' => [
        ProductCRUDProvider::class,
        ProductCategoryCRUDProvider::class,
        ProductTypeCRUDProvider::class,
        BankGatewayCRUDProvider::class,
        BankGatewayTransactionCRUDProvider::class,
        WalletTransactionCRUDProvider::class,
        CartCRUDProvider::class,
        FileUploadCRUDProvider::class,
    ],

    'controllers' => [
        ProductController::class,
        ProductCategoryController::class,
        ProductTypeController::class,
        BankGatewayController::class,
        BankGatewayTransactionController::class,
        WalletTransactionController::class,
        CartController::class,
        FileUploadController::class,
    ],

    'file_upload_processors' => [
        VideoFileProcessor::class,
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
            'already' => '/me/products',
            'success' => '/me/products',
            'failed' => '/me/current-cart/',

            'increase_success' => '/me/transactions',
            'increase_failed' => '/me/transactions',
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
    ]
];
