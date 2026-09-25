<?php

declare(strict_types=1);

namespace App\Domain\Shared\Attachments\Models;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Count\Models\StockCount;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Shared\Attachments\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran generik (08c, A-68, A-238). Pemiliknya ditunjuk lewat alias
 * pendek `attachable_type` (varchar 40), bukan nama kelas, dan dicari
 * **dengan** cakupan user: lampiran dokumen di luar cakupan tidak ditemukan.
 * Lampiran tidak pernah dihapus fisik (P-03); penggantian cukup menunjuk
 * baris baru.
 */
class Attachment extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, class-string<Model>> */
    public const OWNERS = [
        'material_issue' => MaterialIssue::class,
        'asset_handover' => AssetHandover::class,
        'stock_count' => StockCount::class,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'size_bytes' => 'integer',
        ];
    }

    public static function typeOf(Model $owner): string
    {
        $alias = array_search($owner::class, self::OWNERS, true);

        if ($alias === false) {
            throw new \InvalidArgumentException('Pemilik lampiran tidak dikenal: '.$owner::class);
        }

        return $alias;
    }

    /** Dokumen pemilik dalam cakupan user yang sedang masuk; null = di luar cakupan. */
    public function owner(): ?Model
    {
        $kelas = self::OWNERS[$this->attachable_type] ?? null;

        return $kelas === null ? null : $kelas::query()->find($this->attachable_id);
    }

    public function scopeFor(Builder $query, Model $owner): Builder
    {
        return $query->where('attachable_type', self::typeOf($owner))->where('attachable_id', $owner->getKey());
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
