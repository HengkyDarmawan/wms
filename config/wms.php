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

    // Kanal WhatsApp/SMS platform (A-273, O-15): OTP bukti terima otomatis,
    // kelak notifikasi & approval WhatsApp (Fase 2a). Driver:
    //   none = belum ada penyedia, OTP disampaikan driver (bawaan);
    //   log  = pesan ditulis ke log (lokal/demo);
    //   http = POST ke gateway (token di header, nomor & pesan di body).
    // Company tetap menyalakan sendiri lewat saklar "OTP bukti terima otomatis".
    'messaging' => [
        'driver' => env('WMS_MESSAGING_DRIVER', 'none'),
        'http' => [
            'url' => env('WMS_MESSAGING_URL'),
            'token' => env('WMS_MESSAGING_TOKEN'),
            'token_header' => env('WMS_MESSAGING_TOKEN_HEADER', 'Authorization'),
            'format' => env('WMS_MESSAGING_FORMAT', 'form'),       // form | json
            'phone_field' => env('WMS_MESSAGING_PHONE_FIELD', 'target'),
            'message_field' => env('WMS_MESSAGING_MESSAGE_FIELD', 'message'),
            'extra' => env('WMS_MESSAGING_EXTRA', ''),              // JSON field tetap, mis. {"countryCode":"62"}
            'timeout' => (int) env('WMS_MESSAGING_TIMEOUT', 8),
        ],
    ],

    // WhatsApp Cloud API resmi (Fase 2a, docs/wms/31-whatsapp.md, A-274): satu
    // nomor platform untuk semua company (A-24). Driver:
    //   none  = mati (bawaan);
    //   log   = pesan ditulis ke log + id palsu, alur token/kuota tetap jalan (lokal/demo);
    //   cloud = Graph API Meta (token sistem, phone number id, app secret untuk webhook).
    // Company tetap perlu fitur `whatsapp` dari Super Admin dan izin kejadian dari Admin Company.
    'whatsapp' => [
        'driver' => env('WMS_WA_DRIVER', 'none'),
        'graph_version' => env('WMS_WA_GRAPH_VERSION', 'v23.0'),
        'token' => env('WMS_WA_TOKEN'),
        'phone_number_id' => env('WMS_WA_PHONE_NUMBER_ID'),
        'app_secret' => env('WMS_WA_APP_SECRET'),       // tanda tangan webhook X-Hub-Signature-256
        'verify_token' => env('WMS_WA_VERIFY_TOKEN'),   // verifikasi webhook (hub.verify_token)
        'language' => env('WMS_WA_LANGUAGE', 'id'),
        'timeout' => (int) env('WMS_WA_TIMEOUT', 10),
        // Nama template yang diajukan ke Meta (31-whatsapp §7).
        'templates' => [
            'notification' => env('WMS_WA_TPL_NOTIFICATION', 'wms_notifikasi'),
            'approval' => env('WMS_WA_TPL_APPROVAL', 'wms_approval'),
            'digest' => env('WMS_WA_TPL_DIGEST', 'wms_ringkasan'),
            'code' => env('WMS_WA_TPL_CODE', 'wms_kode'),
        ],
        // Masa berlaku token tombol approval, jam (dibatasi batas waktu tugas).
        'approval_token_hours' => 72,
    ],

];
