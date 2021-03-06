<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    | Supported: "local", "ftp", "s3", "rackspace"
    |
    */

    'default' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => 's3',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => 'your-key',
            'secret' => 'your-secret',
            'region' => 'your-region',
            'bucket' => 'your-bucket',
        ],
        'skuMapping' => [
            'driver' => 'local',
            'root' => storage_path('marketplace-sku-mapping'),
        ],
        'xml' => [
            'driver' => 'local',
            'root' => storage_path('marketplace-xml'),
        ],
        'report' => [
            'driver' => 'local',
            'root' => storage_path('marketplace-report'),
        ],
        'productUpload' => [
            'driver' => 'local',
            'root' => storage_path('bulk-product-upload'),
        ],
        'priceUpload' => [
            'driver' => 'local',
            'root' => storage_path('bulk-price-upload'),
        ],
        'mattelSkuMappingUpload' => [
            'driver' => 'local',
            'root' => storage_path('mattel-sku-mapping-upload'),
        ],
        'platformMarketInventoryUpload' => [
            'driver' => 'local',
            'root' => storage_path('platform-market-inventory-upload'),
        ],
        'merchant' => [
            'driver' => 'local',
            'root' => storage_path('merchant'),
        ],
        'iwms' => [
            'driver' => 'local',
            'root' => storage_path('iwms'),
        ],
        'pickList' => [
            'driver' => 'local',
            'root' => storage_path('picklist'),
        ],
        'product' => [
            'driver' => 'local',
            'root' => storage_path('product'),
        ],
        'fulfillmentOrderFeed' => [
            'driver' => 'local',
            'root' => storage_path('fulfillment-order-feed'),
        ],
        'settlementPreview' => [
            'driver' => 'local',
            'root' => storage_path('settlement_preview'),
        ]
    ],

];
