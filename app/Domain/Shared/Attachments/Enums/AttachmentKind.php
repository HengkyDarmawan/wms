<?php

declare(strict_types=1);

namespace App\Domain\Shared\Attachments\Enums;

/** Jenis lampiran — Katalog §3 `attachment_kind` (08c `attachments.kind`). */
enum AttachmentKind: string
{
    case Photo = 'photo';
    case Document = 'document';
    case Signature = 'signature';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Foto',
            self::Document => 'Dokumen',
            self::Signature => 'Tanda tangan',
            self::Report => 'Laporan',
        };
    }
}
