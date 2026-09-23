<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan modul Picking & Shipment.
 *
 * Kode aturannya ikut dibawa supaya layar bisa menyebutnya: di gudang, "gagal"
 * tanpa sebab membuat orang mengulang tindakan yang sama sampai frustrasi.
 */
class ShipmentRuleException extends RuntimeException
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
