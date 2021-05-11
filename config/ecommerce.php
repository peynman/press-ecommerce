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
        \Larapress\ECommerce\Controllers\CartPurchasingController::class,
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
        'product_reviews' => [
            'name' => 'product-reviews',
        ],
        'wallet_transactions' => [
            'name' => 'wallet-transactions',
        ],
        'gift_codes' => [
            'name' => 'gift-codes',
        ]
    ],

    'repository' => [
        'limit' => 50,
        'max_limit' => 200,
        'min_limit' => 5,
    ],

    'customer_role_id' => 3,

    'banking' => [
        'ports' => [
            'zarinpal' => Larapress\ECommerce\Services\Banking\Ports\Zarrinpal\ZarrinPalPortInterface::class
        ],

        'currency' => [
            'id' => 1,
            'title' => 'تومان'
        ],

        'available_currencies' => [
            [
                'id' => 1,
                'title' => 'تومان',
            ],
        ],


        'redirect' => [
            'already' => '/me/carts',
            'success' => '/me/products',
            'failed' => '/me/current-cart/',

            'increase_success' => '/me/carts',
            'increase_failed' => '/me/carts',
        ],

        'default_gateway' => 1,

        'registeration_gift' => [
            'amount' => 0,
            'currency' => 1,
        ],

        'profle_gift' => [
            'amount' => 0,
            'currency' => 1,
        ]
    ],
];
