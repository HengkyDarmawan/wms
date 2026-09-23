<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use Carbon\CarbonInterface;

/**
 * Satu permintaan pergerakan stok.
 *
 * Dibuat sebagai objek, bukan daftar argumen panjang, karena setiap modul
 * dokumen akan memanggilnya dan urutan argumen yang panjang adalah cara
 * paling mudah memasukkan bin asal ke tempat bin tujuan.
 *
 * Arah ditentukan pasangan bin, bukan tanda bilangan (BR-LED-02):
 * - asal null, tujuan terisi  → masuk dari luar
 * - asal terisi, tujuan null  → keluar
 * - keduanya terisi           → pindah
 */
class MovementRequest
{
    public function __construct(
        public readonly Item $item,
        public readonly float $qtyBase,
        public readonly ?int $fromBinId = null,
        public readonly ?int $toBinId = null,
        public readonly StockStatus $stockStatus = StockStatus::Available,
        /**
         * Kondisi barang di bin ASAL, bila berbeda dari kondisi tujuan.
         *
         * Dipakai saat kondisi berubah tanpa barangnya berpindah: QC menolak
         * barang di bin Karantina, atau barang kembali dari pengiriman dalam
         * keadaan rusak. Kosong berarti kondisinya tidak berubah.
         */
        public readonly ?StockStatus $fromStockStatus = null,
        public readonly ?int $lotId = null,
        public readonly ?int $serialId = null,
        public readonly ?int $pieceId = null,
        public readonly ?int $projectId = null,
        public readonly ?string $documentType = null,
        public readonly ?int $documentId = null,
        public readonly ?int $documentLineId = null,
        public readonly ?string $documentNumber = null,
        public readonly ?int $reasonCodeId = null,
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly ?User $performedBy = null,
        public readonly ?StockEventType $eventType = null,
        /** @var array<string, mixed> */
        public readonly array $eventPayload = [],
        public readonly ?string $notes = null,
        /**
         * Diisi HANYA oleh StockLedger::reverse(). Kartu stok append-only, jadi
         * penunjuk ke baris asal harus ikut saat insert, bukan ditulis belakangan.
         */
        public readonly ?int $reversesMovementId = null,
    ) {}

    /** Kondisi tujuan boleh berbeda dari kondisi asal, mis. saat QC menolak barang. */
    public function withStatus(StockStatus $status): self
    {
        return new self(
            $this->item, $this->qtyBase, $this->fromBinId, $this->toBinId, $status,
            $this->fromStockStatus, $this->lotId, $this->serialId, $this->pieceId, $this->projectId,
            $this->documentType, $this->documentId, $this->documentLineId,
            $this->documentNumber, $this->reasonCodeId, $this->occurredAt,
            $this->performedBy, $this->eventType, $this->eventPayload, $this->notes,
            $this->reversesMovementId,
        );
    }

    /** Kondisi yang dikurangi dari bin asal. */
    public function sourceStatus(): StockStatus
    {
        return $this->fromStockStatus ?? $this->stockStatus;
    }

    /** Barang berubah kondisi tanpa berpindah bin (BR-LED-02). */
    public function isConditionChange(): bool
    {
        return $this->fromBinId !== null
            && $this->fromBinId === $this->toBinId
            && $this->sourceStatus() !== $this->stockStatus;
    }

    public function isInbound(): bool
    {
        return $this->fromBinId === null;
    }

    public function isOutbound(): bool
    {
        return $this->toBinId === null;
    }
}
