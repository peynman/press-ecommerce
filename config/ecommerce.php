<?php

return [
    // banking settings
    'banking' => [
        // available ports
        'ports' => [
            'zarinpal' => Larapress\ECommerce\Services\Banking\Ports\Zarrinpal\ZarrinPalPortInterface::class
        ],

        // default currency
        'currency' => [
            'id' => 1,
            'title' => 'تومان'
        ],

        // available currencies
        'available_currencies' => [
            [
                'id' => 1,
                'title' => 'تومان',
            ],
        ],

        // url redirects after cart purchase
        'redirect' => [
            'success' => '/me/products',
            'failed' => '/me/current-cart/',
            'canceled' => '/me/current-cart/',
        ],

        // default bank gateway id
        'default_gateway' => null,
    ],

    // purchasing cart options
    'carts' => [
        // reset purchasing cart after 72 hours
        'reset_purchasing_after' => 72,
    ],

    // delivery agents
    'delivery_agents' => [
        'alopeyk' => \Larapress\ECommerce\Services\Cart\DeliveryAgent\AloPeyk\AloPeykDeliveryAgent::class,
        'alopeyk_user' => \Larapress\ECommerce\Services\Cart\DeliveryAgent\AloPeyk\AloPeykUserDeliveryAgent::class,
        'poste_pishtaz' => \Larapress\ECommerce\Services\Cart\DeliveryAgent\PostePishtaz\PostePishtazDeliveryAgent::class,
        'poste_pishtaz_user' => \Larapress\ECommerce\Services\Cart\DeliveryAgent\PostePishtaz\PostePishtazUserDeliveryAgent::class,
        'snap_user' => \Larapress\ECommerce\Services\Cart\DeliveryAgent\Snap\SnapUserDeliveryAgent::class,
    ],

    // product management
    'products' => [
        'sorts' => [
            'publish_at_desc' => Larapress\ECommerce\Services\Product\Sort\SortByPublishDesc::class,
            'publish_at_asc' => Larapress\ECommerce\Services\Product\Sort\SortByPublishAsc::class,
            'stars_asc' => Larapress\ECommerce\Services\Product\Sort\SortByStarsAsc::class,
            'stars_desc' => Larapress\ECommerce\Services\Product\Sort\SortByStarsDesc::class,
            'price_asc' => Larapress\ECommerce\Services\Product\Sort\SortByPriceAsc::class,
            'price_desc' => Larapress\ECommerce\Services\Product\Sort\SortByPriceDesc::class,
            'purchases_asc' => Larapress\ECommerce\Services\Product\Sort\SortByPurchasesAsc::class,
            'purchases_desc' => Larapress\ECommerce\Services\Product\Sort\SortByPurchasesDesc::class,
        ],
        // product owner role ids
        'product_owner_role_ids' => [9, 10],
    ],

    // cart sms notifications
    'sms' => [
        // sms gateway id for cart purchase success
        'cart_success_gateway_id' => null,
        // sms gateway id for cart posted
        'cart_posted_gateway_id' => null,
    ],

    // product reviews management
    'product_reviews' => [
        // signed in users can only post reviews
        'review_users_only' => true,

        // users are allowed to review purchased products only
        'review_purchased_product' => true,

        // automatically confirm reviews for displaying
        'review_auto_confirm' => true,

        // number of reviews loaded per page
        'reviews_per_page' => 5,
    ],

    // ecommerce metric reports
    'reports' => [
        'group' => 'ecommerce',
        // metric types
        'bank_gateway_transactions' => 1,
        'carts' => 2,
        'gift_codes' => 3,
    ],

    // crud resources in package
    'routes' => [
        'bank_gateways' => [
            'name' => 'bank-gateways',
            'model' => \Larapress\ECommerce\Models\BankGateway::class,
            'provider' => \Larapress\ECommerce\CRUD\BankGatewayCRUDProvider::class,
        ],
        'bank_gateway_transactions' => [
            'name' => 'bank-gateway-transactions',
            'model' => \Larapress\ECommerce\Models\BankGatewayTransaction::class,
            'provider' => \Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider::class,
        ],
        'carts' => [
            'name' => 'carts',
            'model' => \Larapress\ECommerce\Models\Cart::class,
            'provider' => \Larapress\ECommerce\CRUD\CartCRUDProvider::class,
        ],
        'products' => [
            'name' => 'products',
            'model' => \Larapress\ECommerce\Models\Product::class,
            'provider' => \Larapress\ECommerce\CRUD\ProductCRUDProvider::class,
        ],
        'product_categories' => [
            'name' => 'product-categories',
            'model' => \Larapress\ECommerce\Models\ProductCategory::class,
            'provider' => \Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider::class,
        ],
        'product_types' => [
            'name' => 'product-types',
            'model' => \Larapress\ECommerce\Models\ProductType::class,
            'provider' => \Larapress\ECommerce\CRUD\ProductTypeCRUDProvider::class,
        ],
        'product_reviews' => [
            'name' => 'product-reviews',
            'model' => \Larapress\ECommerce\Models\ProductReview::class,
            'provider' => \Larapress\ECommerce\CRUD\ProductReviewCRUDProvider::class,
        ],
        'wallet_transactions' => [
            'name' => 'wallet-transactions',
            'model' => \Larapress\ECommerce\Models\WalletTransaction::class,
            'provider' => \Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider::class,
        ],
        'gift_codes' => [
            'name' => 'gift-codes',
            'model' => \Larapress\ECommerce\Models\GiftCode::class,
            'provider' => \Larapress\ECommerce\CRUD\GiftCodeCRUDProvider::class,
        ],
        'gift_code_usage' => [
            'name' => 'gift-code-usage',
            'model' => \Larapress\ECommerce\Models\GiftCodeUse::class,
            'provider' => \Larapress\ECommerce\CRUD\GiftCodeUsageCRUDProvider::class,
        ],
    ],

    'permissions' => [
        \Larapress\ECommerce\CRUD\ProductCRUDProvider::class,
        \Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider::class,
        \Larapress\ECommerce\CRUD\ProductTypeCRUDProvider::class,
        \Larapress\ECommerce\CRUD\ProductReviewCRUDProvider::class,
        \Larapress\ECommerce\CRUD\BankGatewayCRUDProvider::class,
        \Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider::class,
        \Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider::class,
        \Larapress\ECommerce\CRUD\CartCRUDProvider::class,
        \Larapress\ECommerce\CRUD\GiftCodeCRUDProvider::class,
    ],
];
