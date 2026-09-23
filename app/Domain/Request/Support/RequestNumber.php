<?php

declare(strict_types=1);

namespace App\Domain\Request\Support;

use App\Domain\Master\Models\Project;
use App\Domain\Stock\Support\DocumentNumber;

/**
 * Nomor REQ (BR-GEN-06): `REQ/<kode proyek>/<yymm>/<urut>`.
 *
 * Segmennya kode proyek, bukan kode gudang seperti dokumen gudang: satu REQ
 * milik satu proyek ([BR-REQ-01]) dan barisnya boleh mengambil dari beberapa
 * gudang sekaligus, sehingga gudang bukan penanda yang bermakna di sini.
 */
class RequestNumber
{
    public const JENIS = 'REQ';

    public function __construct(private readonly DocumentNumber $nomor) {}

    public function next(Project $project): string
    {
        return $this->nomor->next(self::JENIS, (string) $project->code);
    }
}
