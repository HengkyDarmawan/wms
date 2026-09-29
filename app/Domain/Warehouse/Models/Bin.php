<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Enums\BinMergeDirection;
use App\Domain\Warehouse\Enums\BinMergeType;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property ?BinMergeDirection $merge_direction
 * @property ?BinMergeType $merge_type
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
            'merge_direction' => BinMergeDirection::class,
            'merge_type' => BinMergeType::class,
            'width_m' => 'decimal:2',
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

    /**
     * Gabung Bin (K-B, A-359): bin utama tempat stok bin tergabung ini dicatat.
     * Kolom `occupied_by_bin_id` warisan A-255 ("ikut terpakai").
     */
    public function mainBin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'occupied_by_bin_id')->withoutGlobalScopes();
    }

    /** Bin tergabung milik bin utama ini. */
    public function mergedBins(): HasMany
    {
        return $this->hasMany(self::class, 'occupied_by_bin_id')->withoutGlobalScopes();
    }

    /** Bin Tergabung: tidak menyimpan stok sendiri, stoknya di bin utama. */
    public function isMerged(): bool
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
        // A-359: bin tergabung tidak menerima pergerakan sendiri — stoknya di bin utama.
        return $this->bin_status->acceptsMovement() && ! $this->isMerged();
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

    public function blocksOnOverCapacity(): bool
    {
        return $this->capacityMode() === CapacityMode::Block;
    }

    /**
     * Apakah penempatan sejumlah ini melampaui kapasitas yang ditetapkan?
     * Kapasitas yang kosong berarti tidak dibatasi. Angka yang dibandingkan
     * adalah **seluruh isi bin** (keputusan #10, A-361); bin utama gabungan
     * memakai {@see effectiveCapacity()}.
     */
    public function exceedsCapacity(float $qty, ?float $weight = null, ?float $volume = null, ?float $length = null): bool
    {
        $batas = [
            [$qty, $this->effectiveCapacity('capacity_qty')],
            [$weight, $this->effectiveCapacity('capacity_weight')],
            [$volume, $this->effectiveCapacity('capacity_volume')],
            [$length, $this->effectiveCapacity('capacity_length')],
        ];

        foreach ($batas as [$nilai, $kapasitas]) {
            if ($nilai !== null && $kapasitas !== null && (float) $nilai > (float) $kapasitas) {
                return true;
            }
        }

        return false;
    }

    /**
     * A-361: kapasitas bin utama gabungan = kapasitas bin utama + semua bin
     * tergabungnya. Bila salah satu kosong (tanpa batas), hasilnya tanpa batas.
     */
    public function effectiveCapacity(string $kolom): ?float
    {
        $sendiri = $this->getAttribute($kolom);

        if ($sendiri === null) {
            return null;
        }

        if (! $this->exists) {
            return (float) $sendiri;
        }

        $tergabung = $this->loadMissing('mergedBins')->getRelation('mergedBins');

        if ($tergabung->contains(fn (self $b) => $b->getAttribute($kolom) === null)) {
            return null;
        }

        return round((float) $sendiri + (float) $tergabung->sum(fn (self $b) => (float) $b->getAttribute($kolom)), 4);
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
                'merge_direction', 'merge_type', 'width_m',
            ])
            ->logOnlyDirty();
    }
}
