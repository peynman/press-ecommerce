<?php

use Larapress\ECommerce\Controllers\BankGatewayController;
use Larapress\ECommerce\Controllers\BankGatewayTransactionController;
use Larapress\ECommerce\Controllers\CartController;
use Larapress\ECommerce\Controllers\ProductCategoryController;
use Larapress\ECommerce\Controllers\ProductController;
use Larapress\ECommerce\Controllers\ProductTypeController;
use Larapress\ECommerce\Controllers\WalletTransactionController;
use Larapress\ECommerce\CRUD\BankGatewayCRUDProvider;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\CRUD\ProductTypeCRUDProvider;
use Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider;

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
    ],

    'permissions' => [
        ProductCRUDProvider::class,
        ProductCategoryCRUDProvider::class,
        ProductTypeCRUDProvider::class,
        BankGatewayCRUDProvider::class,
        BankGatewayTransactionCRUDProvider::class,
        WalletTransactionCRUDProvider::class,
        CartCRUDProvider::class,
    ],

    'controllers' => [
        ProductController::class,
        ProductCategoryController::class,
        ProductTypeController::class,
        BankGatewayController::class,
        BankGatewayTransactionController::class,
        WalletTransactionController::class,
        CartController::class,
    ],

    'banking' => [
        'ports' => [
            'zarinpal' => Larapress\ECommerce\Services\Ports\Zarrinpal\ZarrinPalPortInterface::class
        ],

        'currency' => [
            'id' => 1,
            'title' => 'تومان'
        ]
    ]
];