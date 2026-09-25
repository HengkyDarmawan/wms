<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan Purchase Request (Katalog §2.15, BR-REQ, BR-GRN, BR-GEN).
 */
class PurchaseRequestRuleException extends RuntimeException
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
