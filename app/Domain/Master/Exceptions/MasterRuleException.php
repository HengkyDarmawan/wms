<?php

declare(strict_types=1);

namespace App\Domain\Master\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis modul Master (BR-MST-01..05 dan BR-STK/BR-CNV
 * yang divalidasi saat menyimpan). Pesan Bahasa Indonesia karena tampil ke user.
 */
class MasterRuleException extends RuntimeException
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
    public static function fields(array $fieldErrors, string $rule = 'BR-STK-11'): self
    {
        return new self(reset($fieldErrors) ?: 'Kombinasi tidak sah.', $rule, $fieldErrors);
    }
}
