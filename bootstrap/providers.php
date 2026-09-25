<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AdjustmentServiceProvider;
use App\Providers\ApprovalServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AssetServiceProvider;
use App\Providers\ConversionServiceProvider;
use App\Providers\CountServiceProvider;
use App\Providers\IssueServiceProvider;
use App\Providers\MasterServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PlatformServiceProvider;
use App\Providers\PurchaseRequestServiceProvider;
use App\Providers\PurchasingServiceProvider;
use App\Providers\ReceiptServiceProvider;
use App\Providers\RequestServiceProvider;
use App\Providers\ReturnServiceProvider;
use App\Providers\ShipmentServiceProvider;
use App\Providers\StockServiceProvider;
use App\Providers\TemplateServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\TransferServiceProvider;
use App\Providers\WarehouseServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    PlatformServiceProvider::class,
    NotificationServiceProvider::class,
    AccessServiceProvider::class,
    MasterServiceProvider::class,
    WarehouseServiceProvider::class,
    StockServiceProvider::class,
    RequestServiceProvider::class,
    ShipmentServiceProvider::class,
    ReceiptServiceProvider::class,
    ApprovalServiceProvider::class,
    AdjustmentServiceProvider::class,
    CountServiceProvider::class,
    TransferServiceProvider::class,
    ReturnServiceProvider::class,
    IssueServiceProvider::class,
    ConversionServiceProvider::class,
    AssetServiceProvider::class,
    PurchaseRequestServiceProvider::class,
    PurchasingServiceProvider::class,
    TemplateServiceProvider::class,
];
