<?php

declare(strict_types=1);

namespace App\Domain\Stock\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan stok (P-01, BR-STK, BR-LED).
 *
 * Dipakai modul dokumen sebagai satu jenis kegagalan yang bisa ditangkap:
 * bila posting ditolak, dokumennya ikut batal, bukan setengah tersimpan.
 */
class LedgerException extends RuntimeException
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
    public static function fields(array $fieldErrors, string $rule = 'BR-STK-01'): self
    {
        return new self(reset($fieldErrors) ?: 'Pergerakan stok tidak sah.', $rule, $fieldErrors);
    }
}
