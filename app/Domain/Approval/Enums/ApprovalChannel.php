<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `approval_channel` (kanal keputusan) dan kanal lapis ERD
 * (`web|whatsapp|both`). WhatsApp adalah Fase 2a: tersimpan, tidak tampil
 * (BR-GEN-10).
 */
enum ApprovalChannel: string
{
    use HasOptions;

    case Web = 'web';
    case Whatsapp = 'whatsapp';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Whatsapp => 'WhatsApp',
            self::Both => 'Web & WhatsApp',
        };
    }
}
