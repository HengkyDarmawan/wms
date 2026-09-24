<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan penyesuaian stok (Katalog §2.12, BR-STK, BR-LED, BR-GEN).
 */
class AdjustmentRuleException extends RuntimeException
{
    /** @param  array<string, string>  $fieldErrors */
    public function __construct(
        public readonly string $rule,
        string $message,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }

    /** @param  array<string, string>  $fieldErrors */
    public static function rule(string $rule, string $message, array $fieldErrors = []): self
    {
        return new self($rule, $message, $fieldErrors);
    }

    public static function field(string $rule, string $field, string $message): self
    {
        return new self($rule, $message, [$field => $message]);
    }
}
