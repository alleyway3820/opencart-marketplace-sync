<?php
/**
 * Configuration Example for OpenCart Marketplace Sync
 *
 * Copy this file to config.php and fill in your actual credentials.
 */
return [
    'opencart' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'your_opencart_db',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'prefix'   => 'oc_',
    ],
    'ebay_flip' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'your_ebayflip_db',
        'username' => 'your_ebayflip_user',
        'password' => 'your_ebayflip_password',
    ],
    'ebay_api' => [
        'environment'  => 'PRODUCTION',
        'app_id'       => 'YOUR_APP_ID',
        'dev_id'       => 'YOUR_DEV_ID',
        'cert_id'      => 'YOUR_CERT_ID',
        'redirect_uri' => 'https://yoursite.com/callback.php',
        'refresh_token' => '',
        'access_token'  => '',
        'token_expiry'  => 0,
    ],
    'images' => [
        'base_path' => '/path/to/opencart/image/',
        'cache_dir' => 'catalog/synced/',
        'max_width' => 1000,
        'max_height' => 1000,
    ],
    'category_map' => [],
    'sync' => [
        'default_category_id' => 20,
        'default_stock_status' => 6,
        'default_tax_class_id' => 9,
        'default_weight_class_id' => 1,
        'default_length_class_id' => 1,
        'default_manufacturer_id' => 0,
        'default_store_id' => 0,
        'default_language_id' => 1,
        'status_active' => 1,
        'minimum_qty' => 1,
        'subtract_stock' => 1,
        'shipping_required' => 1,
        'throttle_ms' => 250,
    ],
];
