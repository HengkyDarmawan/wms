<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pengaturan produk WMS Proyek (bukan per company)
|--------------------------------------------------------------------------
|
| Nilai di sini berlaku untuk seluruh platform, mis. landing page produk di
| domain pusat (docs/wms/30-landing-page.md). Pengaturan per company tetap
| disimpan di database tenant.
|
*/

return [

    // Alamat tujuan tombol "Minta demo" di landing page (A-220). Company
    // dibuat oleh Super Admin, jadi tidak ada pendaftaran mandiri.
    'sales_email' => env('WMS_SALES_EMAIL', 'halo@wms.test'),

];
