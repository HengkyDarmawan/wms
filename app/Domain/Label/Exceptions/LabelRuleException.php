<?php

declare(strict_types=1);

namespace App\Domain\Label\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan label kemasan (BR-LBL). Aksi modul lain (PCK, ISU, GRN)
 * menerjemahkannya ke exception domainnya sendiri supaya layar menampilkannya
 * seperti pesan aturan lain.
 */
class LabelRuleException extends RuntimeException
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
