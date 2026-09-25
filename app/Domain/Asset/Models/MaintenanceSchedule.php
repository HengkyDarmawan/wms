<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Master\Models\Serial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal maintenance `[F2]` (BR-AST-07) — stub (BR-GEN-10): tabel dan model
 * ada, belum ada layar maupun pemicu otomatis.
 */
class MaintenanceSchedule extends Model
{
    protected $table = 'maintenance_schedules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date',
            'performed_at' => 'date',
        ];
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }
}
