<?php

declare(strict_types=1);

namespace App\Domain\Stock\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penghitung nomor dokumen per jenis, segmen, dan periode (AD-13, BR-GEN-06).
 *
 * Barisnya dikunci `SELECT … FOR UPDATE` saat nomor diambil, jadi dua proses
 * yang meminta bersamaan tidak pernah mendapat nomor yang sama.
 */
class DocumentSequence extends Model
{
    protected $table = 'document_sequences';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
