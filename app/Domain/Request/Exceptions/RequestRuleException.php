<?php

declare(strict_types=1);

namespace App\Domain\Request\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan modul Request.
 *
 * Kode aturannya ikut dibawa supaya layar bisa menyebutnya: bila permintaan
 * ditolak, pemohon perlu tahu aturan mana yang menolaknya, bukan sekadar
 * "gagal menyimpan".
 */
class RequestRuleException extends RuntimeException
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

    /** Kesalahan yang menempel pada satu field form. */
    public static function field(string $rule, string $field, string $message): self
    {
        return new self($rule, $message, [$field => $message]);
    }
}
