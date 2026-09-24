<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AdjustmentServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ApprovalServiceProvider;
use App\Providers\CountServiceProvider;
use App\Providers\MasterServiceProvider;
use App\Providers\ReceiptServiceProvider;
use App\Providers\RequestServiceProvider;
use App\Providers\ShipmentServiceProvider;
use App\Providers\StockServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\WarehouseServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
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
];
