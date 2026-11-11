<?php
return [
 'payment_mode' => env('SHOP_PAYMENT_MODE','demo'),
 'shipping_mode' => env('SHOP_SHIPPING_MODE','demo'),
 'mp_access_token' => env('MP_ACCESS_TOKEN'),
 'mp_webhook_secret' => env('MP_WEBHOOK_SECRET'),
 'mp_webhook_url'=>env('MP_WEBHOOK_URL'),
 'mp_sandbox'=>(bool)env('MP_SANDBOX',true),
];
