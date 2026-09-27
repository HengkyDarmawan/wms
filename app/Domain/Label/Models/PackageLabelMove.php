<?php

declare(strict_types=1);

namespace App\Domain\Label\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat label (append-only): diterima, ditaruh, keluar, diterima di gudang
 * tujuan, dibatalkan. Nama kejadian diturunkan dari jenis dokumen dan tanda
 * `qty_change`, jadi tidak butuh enum baru di Katalog.
 */
class PackageLabelMove extends Model
{
    protected $table = 'package_label_moves';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(PackageLabel::class, 'package_label_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class)->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id')->withoutGlobalScopes();
    }

    public function toBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'to_bin_id')->withoutGlobalScopes();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** Nama kejadian untuk layar Telusuri label. */
    public function eventLabel(): string
    {
        $q = (float) $this->qty_change;

        return match (true) {
            $this->reason_code_id !== null && $this->document_type === null => 'Dibatalkan',
            $this->document_type === 'goods_receipt' && $q > 0 && $this->notes === 'transfer' => 'Diterima di gudang tujuan',
            $this->document_type === 'goods_receipt' && $q > 0 => 'Diterima',
            $this->document_type === 'putaway_task' => 'Ditaruh di bin',
            $this->document_type === 'pick_task' && $q < 0 => 'Keluar (dipetik)',
            $this->document_type === 'material_issue' && $q < 0 => 'Keluar (dipakai)',
            $this->document_type === 'material_issue' && $q > 0 => 'Kembali (pembalik pemakaian)',
            $this->document_type === 'goods_return' && $q < 0 => 'Dipilah rusak/waste',
            $this->document_type === null && $q < 0 => 'Dipecah ke label isi',
            $this->document_type === null && $q > 0 => 'Dibuat dari label induk',
            default => 'Perubahan',
        };
    }
}
