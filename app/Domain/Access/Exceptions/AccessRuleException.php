<?php

declare(strict_types=1);

namespace App\Domain\Access\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis modul Access (BR-ACC-01..06).
 * Pesan ditulis Bahasa Indonesia karena ditampilkan ke user.
 */
class AccessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $rule = null)
    {
        parent::__construct($message);
    }

    public static function rule(string $rule, string $message): self
    {
        return new self($message, $rule);
    }
}
