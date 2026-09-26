<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Bin — satuan lokasi terkecil tempat stok disimpan (P-06, Blueprint §6.3).
 *
 * Kodenya diturunkan dari hierarki gudang dan terkunci setelah dibuat
 * (BR-WH-01). Bin virtual `in_transit` dan `on_site` adalah lokasi, bukan
 * status saldo (A-29, BR-STK-02).
 *
 * @property BinType $bin_type
 * @property BinStatus $bin_status
 */
class Bin extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'bins';

    protected $guarded = [];

    /**
     * Nilai bawaan kolom yang jarang diisi saat membuat bin. Tanpa ini,
     * instance yang baru dibuat mengembalikan null sampai di-refresh,
     * padahal di database nilainya sudah false.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'bin_type' => 'storage',
        'bin_status' => 'active',
        'is_virtual' => false,
        'count_flag' => false,
    ];

    protected function casts(): array
    {
        return [
            'bin_type' => BinType::class,
            'bin_status' => BinStatus::class,
            'capacity_qty' => 'decimal:4',
            'capacity_weight' => 'decimal:4',
            'capacity_volume' => 'decimal:4',
            'capacity_length' => 'decimal:4',
            'is_virtual' => 'boolean',
            'count_flag' => 'boolean',
            'occupied_at' => 'datetime',
        ];
    }

    /** BR-ACC-05: bin ikut cakupan gudangnya. */
    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function rackLevel(): BelongsTo
    {
        return $this->belongsTo(RackLevel::class);
    }

    public function storageCategory(): BelongsTo
    {
        return $this->belongsTo(StorageCategory::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Bin utama tempat barang besar dicatat, bila bin ini ikut terpakai olehnya (A-255). */
    public function occupiedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'occupied_by_bin_id')->withoutGlobalScopes();
    }

    public function isOccupied(): bool
    {
        return $this->occupied_by_bin_id !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('bin_status', BinStatus::Active->value);
    }

    public function scopeOfType(Builder $query, BinType $type): Builder
    {
        return $query->where('bin_type', $type->value);
    }

    /** Bin yang ditandai perlu dihitung sesi opname berikutnya (A-67, BR-SJ-02). */
    public function scopeFlaggedForCount(Builder $query): Builder
    {
        return $query->where('count_flag', true);
    }

    /** BR-OPN-02: bin beku menolak PCK, PUT, SJ, dan ISU baru. */
    public function acceptsMovement(): bool
    {
        return $this->bin_status->acceptsMovement();
    }

    /**
     * BR-WH-06 / BR-STK-07: kapasitas ditegakkan menurut kategori penyimpanan.
     * Tanpa kategori, kelebihan kapasitas hanya menjadi peringatan (A-37).
     */
    public function capacityMode(): CapacityMode
    {
        // A-255: mode per bin (mis. bin area alat berat) menimpa kategori.
        if ($this->capacity_mode !== null && ($mode = CapacityMode::tryFrom((string) $this->capacity_mode)) !== null) {
            return $mode;
        }

        return $this->storageCategory?->capacity_mode ?? CapacityMode::Warn;
    }

    /**
     * Kapasitas bin dihitung dari **total isi bin** (bukan per baris saldo)
     * bila mode kapasitasnya diatur per bin — bin area berisi satu unit.
     */
    public function capacityCountsWholeBin(): bool
    {
        return $this->capacity_mode !== null;
    }

    public function blocksOnOverCapacity(): bool
    {
        return $this->capacityMode() === CapacityMode::Block;
    }

    /**
     * Apakah penempatan sejumlah ini melampaui kapasitas yang ditetapkan?
     * Kapasitas yang kosong berarti tidak dibatasi.
     */
    public function exceedsCapacity(float $qty, ?float $weight = null, ?float $volume = null, ?float $length = null): bool
    {
        $batas = [
            [$qty, $this->capacity_qty],
            [$weight, $this->capacity_weight],
            [$volume, $this->capacity_volume],
            [$length, $this->capacity_length],
        ];

        foreach ($batas as [$nilai, $kapasitas]) {
            if ($nilai !== null && $kapasitas !== null && (float) $nilai > (float) $kapasitas) {
                return true;
            }
        }

        return false;
    }

    /** BR-WH-02: bin sistem dan bin virtual tidak boleh dinonaktifkan sembarangan. */
    public function isSystemBin(): bool
    {
        return $this->is_virtual || $this->bin_type->isSystemDefault();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('warehouse')
            ->logOnly([
                'warehouse_id', 'rack_level_id', 'code', 'bin_type', 'bin_status',
                'storage_category_id', 'capacity_qty', 'capacity_weight',
                'capacity_volume', 'capacity_length', 'capacity_mode', 'project_id', 'count_flag', 'occupied_by_bin_id',
            ])
            ->logOnlyDirty();
    }
}
