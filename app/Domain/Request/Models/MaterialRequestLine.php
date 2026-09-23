<?php

declare(strict_types=1);

namespace App\Domain\Request\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\LineOwnership;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Enums\SubstitutionResponse;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Baris REQ — satu kebutuhan: item sekian banyak, di gudang ini, tanggal itu.
 *
 * Baris tidak pernah dihapus (P-03). Yang dibatalkan tetap tampil supaya
 * riwayat permintaan terbaca utuh, termasuk apa yang dulu diminta klien
 * sebelum diganti staf.
 *
 * @property RequestLineStatus $status
 * @property LineOwnership $line_ownership
 * @property ?FulfillmentSource $fulfillment_source
 * @property ?SubstitutionResponse $substitution_response
 */
class MaterialRequestLine extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'material_request_lines';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'open',
        'line_ownership' => 'buy',
        'qty_reserved' => 0,
        'qty_shipped' => 0,
        'qty_received' => 0,
        'qty_backorder' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestLineStatus::class,
            'line_ownership' => LineOwnership::class,
            'fulfillment_source' => FulfillmentSource::class,
            'substitution_response' => SubstitutionResponse::class,
            'required_date' => 'date',
            'promised_date' => 'date',
            'mapped_at' => 'datetime',
            'substituted_at' => 'datetime',
            'substitution_deadline_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancel_confirmed_at' => 'datetime',
            'qty_base' => 'decimal:4',
            'qty_reserved' => 'decimal:4',
            'qty_shipped' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'qty_backorder' => 'decimal:4',
            'nominal_length' => 'decimal:4',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function mapper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function cancelConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_confirmed_by');
    }

    public function splitParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_line_id');
    }

    /** Pecahan baris ini ke gudang sumber lain (A-56). */
    public function splits(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_line_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', RequestLineStatus::Open->value);
    }

    /** BR-REQ-03: baris non-katalog yang masih menunggu pemetaan staf. */
    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->open()->whereNull('item_id');
    }

    /** BR-REQ-05: baris yang belum punya cara pemenuhan, sehingga menahan approval. */
    public function scopeWithoutSource(Builder $query): Builder
    {
        return $query->open()->where(fn (Builder $q) => $q
            ->whereNull('fulfillment_source')
            ->orWhereNull('source_warehouse_id'));
    }

    /** BR-REQ-13: penggantian yang tenggat keberatannya sudah lewat. */
    public function scopeSubstitutionExpired(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('substituted_at')
            ->whereNull('substitution_response')
            ->where('substitution_deadline_at', '<=', now());
    }

    public function scopeCancelRequested(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('cancel_requested_at')
            ->whereNull('cancel_confirmed_at');
    }

    public function isMapped(): bool
    {
        return $this->item_id !== null;
    }

    public function hasSource(): bool
    {
        return $this->fulfillment_source !== null && $this->source_warehouse_id !== null;
    }

    public function isSubstituted(): bool
    {
        return $this->substituted_at !== null;
    }

    /** Klien masih bisa menolak penggantian ini. */
    public function substitutionIsPending(): bool
    {
        return $this->isSubstituted()
            && $this->substitution_response === null
            && $this->substitution_deadline_at?->isFuture() === true;
    }

    public function awaitsCancelConfirmation(): bool
    {
        return $this->cancel_requested_at !== null && $this->cancel_confirmed_at === null;
    }

    /** Sisa yang belum terkirim; dipakai saat melepas reservasi. */
    public function outstandingQty(): float
    {
        return max(0, (float) $this->qty_base - (float) $this->qty_shipped);
    }

    /** Nama yang ditampilkan: item terpetakan, atau teks asli pemohon. */
    public function displayName(): string
    {
        if ($this->relationLoaded('item') && $this->item !== null) {
            return $this->item->code.' — '.$this->item->name;
        }

        return $this->non_catalog_text ?? '—';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('request')
            ->logOnly([
                'item_id', 'qty_base', 'required_date', 'promised_date',
                'source_warehouse_id', 'fulfillment_source', 'line_ownership',
                'substitution_response', 'status',
            ])
            ->logOnlyDirty();
    }
}
