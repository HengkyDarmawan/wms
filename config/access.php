<?php

declare(strict_types=1);

/**
 * Kebijakan modul Access — BR-ACC-06, NFR-04, 10-access §4.
 * Nilai bawaan boleh ditimpa per company lewat pengaturan company (modul Platform).
 */
return [

    'login' => [
        // Jumlah gagal berturut-turut sebelum akun dikunci.
        'max_attempts' => (int) env('ACCESS_LOGIN_MAX_ATTEMPTS', 5),
        // Lama kunci akun dalam menit.
        'lock_minutes' => (int) env('ACCESS_LOCK_MINUTES', 15),
        // Batas laju permintaan login per menit (IP + email) — NFR-04.
        'throttle_per_minute' => (int) env('ACCESS_LOGIN_THROTTLE', 5),
    ],

    'invitation' => [
        'valid_hours' => (int) env('ACCESS_INVITATION_HOURS', 72),
    ],

    'password' => [
        'min_length' => (int) env('ACCESS_PASSWORD_MIN', 10),
        // Berapa password terakhir yang tidak boleh dipakai ulang.
        'history' => (int) env('ACCESS_PASSWORD_HISTORY', 3),
        'reset_token_minutes' => (int) env('ACCESS_RESET_MINUTES', 60),
    ],

    'support_access' => [
        'max_days' => (int) env('ACCESS_SUPPORT_MAX_DAYS', 7),
    ],

    'two_factor' => [
        'max_attempts' => (int) env('ACCESS_2FA_MAX_ATTEMPTS', 5),
        'recovery_codes' => 8,
    ],

];
