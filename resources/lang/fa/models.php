<?php

use Larapress\ECommerce\Models\GiftCode;

return [
    'gift-codes' => [
        'status' => [
            GiftCode::STATUS_AVAILABLE => 'فعال',
            GiftCode::STATUS_EXPIRED => 'منقضی شده',
        ]
    ]
];
