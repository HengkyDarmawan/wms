<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\MeterUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Unit bernomor seri. Untuk item aset, baris ini juga memegang keadaan aset
 * (BR-AST-01), posisi proyek saat dipinjamkan, dan data masa pakai (A-66):
 * umur rencana dalam hari dan/atau jam meter, plus skor kondisi (BR-AST-08).
 *
 * Nilai uang dan penyusutan akuntansi TIDAK disimpan di sini (D-07); WMS hanya
 * memasok pemakaian fisik ke modul Akuntansi.
 *
 * @property AssetState $asset_state
 * @property MeterUnit $meter_unit
 */
class Serial extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'serials';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'asset_state' => AssetState::class,
            'meter_unit' => MeterUnit::class,
            'expiry_date' => 'date',
            'due_return_date' => 'date',
            'acquired_at' => 'date',
            'meter_total' => 'decimal:1',
            'expected_life_days' => 'integer',
            'expected_life_hours' => 'decimal:1',
            'condition_score' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('asset_state', AssetState::Available->value);
    }

    public function scopeOnLoan(Builder $query): Builder
    {
        return $query->where('asset_state', AssetState::OnLoan->value);
    }

    /** Aset yang lewat tanggal kembali (BR-AST-05). */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('asset_state', AssetState::OnLoan->value)
            ->whereNotNull('due_return_date')
            ->whereDate('due_return_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->asset_state === AssetState::OnLoan
            && $this->due_return_date !== null
            && $this->due_return_date->isPast();
    }

    /** Aset ini masih bisa dipinjamkan? (BR-AST-01) */
    public function isLoanable(): bool
    {
        return $this->asset_state === AssetState::Available;
    }

    /**
     * Pemakaian terpakai dalam persen (A-66). Dihitung dari meter bila item
     * punya meter, kalau tidak dari umur kalender. null bila umur rencana kosong.
     */
    public function usagePercent(): ?float
    {
        if ($this->meter_unit !== MeterUnit::None && $this->expected_life_hours !== null
            && (float) $this->expected_life_hours > 0) {
            return round((float) $this->meter_total / (float) $this->expected_life_hours * 100, 1);
        }

        if ($this->expected_life_days !== null && $this->expected_life_days > 0 && $this->acquired_at !== null) {
            $terpakai = $this->acquired_at->startOfDay()->diffInDays(now()->startOfDay());

            return round($terpakai / $this->expected_life_days * 100, 1);
        }

        return null;
    }

    /** Sisa masa pakai dalam persen; 0 bila sudah lewat umur rencana. */
    public function remainingLifePercent(): ?float
    {
        $terpakai = $this->usagePercent();

        return $terpakai === null ? null : max(0.0, round(100 - $terpakai, 1));
    }

    /** BR-AST-08: aset yang perlu diperiksa sebelum dipinjamkan lagi. */
    public function needsAttention(): bool
    {
        $sisa = $this->remainingLifePercent();

        return $this->isOverdue()
            || ($this->condition_score !== null && $this->condition_score <= 2)
            || ($sisa !== null && $sisa <= 10);
    }

    public function stateBadge(): string
    {
        return match ($this->asset_state) {
            AssetState::Available => 'success',
            AssetState::Reserved, AssetState::InTransit, AssetState::Returned => 'info',
            AssetState::OnLoan => 'primary',
            AssetState::Inspection, AssetState::Maintenance => 'warning',
            AssetState::Damaged, AssetState::Lost, AssetState::WrittenOff => 'danger',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly([
                'item_id', 'serial_no', 'asset_state', 'condition_grade', 'lot_id',
                'expiry_date', 'current_project_id', 'due_return_date', 'acquired_at',
                'meter_unit', 'meter_total', 'expected_life_days', 'expected_life_hours',
                'condition_score',
            ])
            ->logOnlyDirty();
    }
}
