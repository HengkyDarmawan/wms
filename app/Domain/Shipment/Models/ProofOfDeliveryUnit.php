<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Shipment\Enums\PodUnitCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kondisi per unit untuk item berserial dan per potong (A-64).
 *
 * Barang berserial tidak bisa dihitung "tiga baik satu rusak": yang rusak
 * adalah serial tertentu, dan itulah yang harus tercatat agar riwayat unitnya
 * tetap utuh.
 *
 * @property PodUnitCondition $condition
 */
class ProofOfDeliveryUnit extends Model
{
    use HasFactory;

    protected $table = 'proof_of_delivery_units';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['condition' => PodUnitCondition::class];
    }

    public function proofLine(): BelongsTo
    {
        return $this->belongsTo(ProofOfDeliveryLine::class, 'proof_of_delivery_line_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
