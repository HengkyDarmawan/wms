<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * Jejak tindakan Super Admin di database pusat (NFR-03). Koneksi dipatok ke
 * `central` supaya tetap tertulis ke pusat walau tenancy sedang aktif.
 */
class PlatformAuditLog extends Activity
{
    protected $connection = 'central';

    protected $table = 'audit_logs';
}
