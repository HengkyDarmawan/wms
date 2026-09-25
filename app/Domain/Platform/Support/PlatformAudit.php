<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Database\Eloquent\Model;

/** Menulis jejak tindakan platform ke `audit_logs` pusat (NFR-03, A-182). */
class PlatformAudit
{
    /** @param  array<string, mixed>  $properties */
    public static function record(string $description, ?Model $subject = null, ?PlatformUser $causer = null, array $properties = []): PlatformAuditLog
    {
        $log = new PlatformAuditLog;
        $log->forceFill([
            'log_name' => 'platform',
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'properties' => $properties,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ])->save();

        return $log;
    }
}
