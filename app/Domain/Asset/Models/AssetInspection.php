<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\ConditionGrade;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Serial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemeriksaan aset (ERD 08c `asset_inspections`, BR-AST-03, BR-AST-08): grade
 * A–D, skor kondisi 0–100 %, catatan per komponen, foto, meter kembali. Ini
 * riwayat kondisi aset untuk tim maintenance (A-66).
 */
class AssetInspection extends Model
{
    protected $table = 'asset_inspections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'condition_grade' => ConditionGrade::class,
            'condition_score' => 'integer',
            'component_notes' => 'array',
            'meter_in' => 'decimal:1',
            'resulting_state' => AssetState::class,
            'inspected_at' => 'datetime',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(AssetHandover::class, 'asset_handover_id')->withoutGlobalScopes();
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
