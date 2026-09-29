<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan **Buka Tempat Khusus** (A-367): Kepala Gudang (izin
 * `adjustment.approve`) menaruh barang lain di bin yang ditandai Khusus Barang
 * Ini — dengan alasan wajib, dokumen/konteks, dan waktu. Hanya ditambah.
 */
class StorageDedicationOverride extends Model
{
    protected $table = 'storage_dedication_overrides';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class)->withoutGlobalScopes();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
