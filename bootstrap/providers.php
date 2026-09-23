<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\MasterServiceProvider;
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
];
