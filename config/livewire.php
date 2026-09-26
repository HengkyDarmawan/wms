<?php

/*
 * Hanya kunci yang berbeda dari bawaan Livewire; kunci lain digabung dari
 * vendor/livewire/livewire/config/livewire.php (mergeConfigFrom).
 */

return [

    /*
     * A-257: unggahan sementara boleh sampai 20 MB (foto kamera HP) karena
     * foto dikompres otomatis menjadi ≤ 5 MB saat disimpan (A-23,
     * StoreUpload + ImageCompressor). Bawaan Livewire 12 MB.
     */
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:20480'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
