<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Conversion\Models\Conversion;

/**
 * Katalog §2.10 memisahkan dua jalan dari `draft`: `conversion.submit` bila
 * **ada aturan approval** yang cocok, `conversion.complete` bila tidak ada
 * (A-153). Kelas ini satu-satunya yang memutuskan jalan mana yang berlaku,
 * dipakai aksi dan policy sehingga tombol di layar selalu sama dengan guard.
 */
class ConversionApprovalRoute
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly ConversionApprovalHandler $handler,
    ) {}

    public function rule(Conversion $cnv): ?ApprovalRule
    {
        return $this->engine->matchRule(ApprovalDocumentType::Conversion, $this->handler->context($cnv))['rule'];
    }

    public function needsApproval(Conversion $cnv): bool
    {
        return $this->rule($cnv) !== null;
    }
}
