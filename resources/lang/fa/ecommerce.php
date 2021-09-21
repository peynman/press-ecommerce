<?php

return [
    'banking' => [
        'messages' => [
            'bank_forwared' => 'درخواست خرید سبد با شماره :cart_id',
            'wallet_descriptions' => [
                'cart_increased' => 'افزایش موجودی برای سبد خرید :cart_id',
                'cart_purchased' => 'خرید محصولات در سبد خرید :cart_id',
                'cart_purchased_product' => 'خرید مخصول :product در سبد :cart_id',
            ]
        ],
    ],

    'messaging' => [
        'sms_send_error' => 'خطایی در ارسال پیامک بوجود آمد، لطفا بعدا تلاش کنید ',
        'purchase_success' => 'خرید شما با موفقیت انجام شد. میتوانید به محتوای مورد نظر دسترسی داشته باشید.',
        'purchase_failed' => 'خطایی در عملیات بوجود آمد، لطفا بعدا دباره تلاش کنید، چنانچه مبلغی از شما کسر شده باشد، طی ۷۲ ساعت به حساب شما بازخواهد گشت',
        'purchase_canceled' => 'خرید شما ثبت نشد.',
    ],

    'products' => [
        'courses' => [
            'send_form_title' => 'ارسال تکلیف کلاس با شناسه :session_id'
        ],
        'review' => [
            'success' => 'نظر شما ارسال شد',
            'success_preview' => 'نظر شما ارسال شد و پس از تایید در سایت نمایش داده خواهد شد',
        ],
    ]
];
