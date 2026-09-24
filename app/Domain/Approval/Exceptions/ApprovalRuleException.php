<?php

declare(strict_types=1);

namespace App\Domain\Approval\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan approval (BR-APR, BR-GEN-11). Kode aturan ikut dibawa
 * supaya layar dokumen mana pun bisa menyebutnya.
 */
class ApprovalRuleException extends RuntimeException
{
    /** @param  array<string, string>  $fieldErrors */
    public function __construct(
        public readonly string $rule,
        string $message,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }

    public static function rule(string $rule, string $message): self
    {
        return new self($rule, $message);
    }

    public static function field(string $rule, string $field, string $message): self
    {
        return new self($rule, $message, [$field => $message]);
    }
}
