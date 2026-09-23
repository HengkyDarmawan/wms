<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis modul Warehouse (BR-WH-01..07 dan BR-STK/BR-GEN
 * yang divalidasi saat menyimpan). Pesan Bahasa Indonesia karena tampil ke user.
 */
class WarehouseRuleException extends RuntimeException
{
    /** @param  array<string, string>  $fieldErrors */
    public function __construct(
        string $message,
        public readonly ?string $rule = null,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }

    public static function rule(string $rule, string $message): self
    {
        return new self($message, $rule);
    }

    /** @param  array<string, string>  $fieldErrors */
    public static function fields(array $fieldErrors, string $rule = 'BR-WH-01'): self
    {
        return new self(reset($fieldErrors) ?: 'Nilai tidak sah.', $rule, $fieldErrors);
    }
}
