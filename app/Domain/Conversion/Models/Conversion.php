<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * CNV — konversi material (Katalog Status §2.10, glosarium `conversion`,
 * Blueprint §6.7, D-10, D-11).
 *
 * Input keluar dari bin penyimpanan; output dan offcut masuk sebagai potongan
 * baru bersilsilah; waste masuk bin Waste; kerf hilang. Semuanya lewat
 * `StockLedger` (P-01) dengan kejadian `material_converted` saat `completed`.
 * Koreksi setelah `completed` hanya lewat CNV pembalik bila tidak ada hasil
 * yang sudah dipakai (BR-CNV-05, A-157).
 *
 * Cakupan (BR-ACC-05): gudang dan proyek harus sama-sama dalam cakupan.
 *
 * @property ConversionStatus $status
 * @property ConversionType $conversion_type
 */
class Conversion extends Model
{
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'conversions';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'conversion_type' => 'cut',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversionStatus::class,
            'conversion_type' => ConversionType::class,
            'total_input' => 'decimal:4',
            'total_output' => 'decimal:4',
            'total_offcut' => 'decimal:4',
            'total_waste' => 'decimal:4',
            'total_kerf' => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public static function scopeProjectColumn(): ?string
    {
        return 'project_id';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function rejectReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reject_reason_id');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ApprovalSnapshot::class, 'approval_snapshot_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(ConversionInput::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ConversionOutput::class);
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    /** CNV pembalik yang masih berlaku (draf, menunggu, atau selesai) atas CNV ini. */
    public function activeReversal(): ?self
    {
        return $this->reversals()->where('status', '!=', ConversionStatus::Cancelled->value)->orderBy('id')->first();
    }

    public function isReversed(): bool
    {
        return $this->reversals()->where('status', ConversionStatus::Completed->value)->exists();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === ConversionStatus::PendingApproval
            && app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::Conversion, (int) $this->id) !== null;
    }

    /** Jumlah per jenis baris sisa/hasil, dari baris dokumen. */
    public function totalFor(ConversionOutputKind $kind): float
    {
        return round((float) $this->outputs()->where('output_kind', $kind->value)->sum('qty_base'), 4);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('conversion')
            ->logOnly(['number', 'status', 'completed_by', 'approved_by'])
            ->logOnlyDirty();
    }
}
