<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Models;

use App\Domain\Platform\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pesan WhatsApp di database **pusat** (ERD 08a, 31-whatsapp §3): satu
 * baris per pesan keluar/masuk, per company dan per kategori — dasar kuota
 * bulanan (BR-WA-03) dan penentuan harga paket (O-04). Status diperbarui
 * webhook Meta (`sent` → `delivered` → `read`, atau `failed`).
 */
class WaMessageLog extends Model
{
    public const CATEGORY_UTILITY = 'utility_template';

    public const CATEGORY_AUTH = 'authentication';

    public const CATEGORY_SERVICE = 'service';

    public const CATEGORY_INBOUND = 'inbound';

    /** Kategori yang dihitung kuota: pesan template yang dimulai bisnis (A-278). */
    public const KUOTA = [self::CATEGORY_UTILITY, self::CATEGORY_AUTH];

    protected $connection = 'central';

    protected $table = 'wa_message_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'cost_units' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Bulan kalender berjalan di zona platform (A-278). */
    public function scopeThisMonth(Builder $q): Builder
    {
        $awal = now('Asia/Jakarta')->startOfMonth()->utc();

        return $q->where('created_at', '>=', $awal);
    }
}
