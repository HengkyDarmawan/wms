<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Reservasi stok (BR-STK-03 s.d. BR-STK-05).
 *
 * Menjanjikan barang bukan memindahkannya, jadi ini tidak menyentuh kartu stok
 * sama sekali. Yang dijaga hanya satu hal: jumlah yang dijanjikan tidak boleh
 * melebihi yang benar-benar tersedia.
 */
class ManageReservation
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * Reservasi lunak — dibuat saat dokumen disetujui, menyebut item dan gudang saja.
     */
    public function reserveSoft(
        Item $item,
        Warehouse $warehouse,
        float $qtyBase,
        string $documentType,
        int $documentId,
        ?int $documentLineId = null,
        ?User $actor = null,
    ): StockReservation {
        return $this->buat($item, $warehouse, $qtyBase, ReservationLevel::Soft, [
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_line_id' => $documentLineId,
        ], $actor);
    }

    /**
     * Alokasi keras — dibuat saat picking, sudah menunjuk bin dan turunannya.
     *
     * @param  array<string, mixed>  $target  bin_id, lot_id, serial_id, piece_id
     */
    public function reserveHard(
        Item $item,
        Warehouse $warehouse,
        float $qtyBase,
        array $target,
        string $documentType,
        int $documentId,
        ?int $documentLineId = null,
        ?User $actor = null,
    ): StockReservation {
        if (($target['bin_id'] ?? null) === null) {
            throw LedgerException::rule('BR-STK-04', 'Alokasi keras wajib menunjuk bin.');
        }

        return $this->buat($item, $warehouse, $qtyBase, ReservationLevel::Hard, [
            'bin_id' => $target['bin_id'],
            'lot_id' => $target['lot_id'] ?? null,
            'serial_id' => $target['serial_id'] ?? null,
            'piece_id' => $target['piece_id'] ?? null,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_line_id' => $documentLineId,
        ], $actor);
    }

    /**
     * BR-STK-05 — reservasi berubah menjadi pergerakan; tidak lagi mengurangi
     * stok tersedia karena barangnya memang sudah pindah.
     */
    public function consume(StockReservation $reservation, ?User $actor = null): StockReservation
    {
        if ($reservation->status !== ReservationStatus::Active) {
            throw LedgerException::rule(
                'BR-STK-05',
                'Hanya reservasi aktif yang bisa ditandai terpenuhi.',
            );
        }

        $reservation->forceFill(['status' => ReservationStatus::Consumed])->save();

        activity('stock')
            ->performedOn($reservation)
            ->causedBy($actor)
            ->log('Reservasi terpenuhi');

        return $reservation->refresh();
    }

    /** BR-STK-05 — pelepasan selalu punya alasan, tidak pernah otomatis. */
    public function release(StockReservation $reservation, string $reason, ?User $actor = null): StockReservation
    {
        if (trim($reason) === '') {
            throw LedgerException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        if ($reservation->status !== ReservationStatus::Active) {
            throw LedgerException::rule('BR-STK-05', 'Reservasi ini sudah tidak aktif.');
        }

        $reservation->forceFill([
            'status' => ReservationStatus::Released,
            'released_reason' => $reason,
            'released_at' => now(),
        ])->save();

        activity('stock')
            ->performedOn($reservation)
            ->causedBy($actor)
            ->withProperties(['reason' => $reason])
            ->log('Reservasi dilepas');

        return $reservation->refresh();
    }

    /**
     * Melepas seluruh reservasi aktif milik satu dokumen sekaligus.
     *
     * Dipakai modul dokumen saat dokumennya ditolak, dibatalkan, atau ditutup
     * dengan sisa (BR-STK-05).
     */
    public function releaseForDocument(string $documentType, int $documentId, string $reason, ?User $actor = null): int
    {
        $reservasi = StockReservation::query()
            ->active()
            ->forDocument($documentType, $documentId)
            ->get();

        foreach ($reservasi as $baris) {
            $this->release($baris, $reason, $actor);
        }

        return $reservasi->count();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function buat(
        Item $item,
        Warehouse $warehouse,
        float $qtyBase,
        ReservationLevel $level,
        array $attributes,
        ?User $actor,
    ): StockReservation {
        if ($qtyBase <= 0) {
            throw LedgerException::rule('BR-STK-03', 'Jumlah reservasi harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($item, $warehouse, $qtyBase, $level, $attributes, $actor): StockReservation {
            // BR-STK-03: tidak boleh menjanjikan lebih dari yang tersedia.
            $tersedia = $this->ledger->availableQty((int) $item->id, (int) $warehouse->id);

            if ($qtyBase > $tersedia) {
                throw LedgerException::rule(
                    'BR-STK-03',
                    'Stok tersedia di gudang '.$warehouse->code.' hanya '
                    .rtrim(rtrim(number_format($tersedia, 4, '.', ''), '0'), '.')
                    .', diminta '.rtrim(rtrim(number_format($qtyBase, 4, '.', ''), '0'), '.').'.',
                );
            }

            $reservasi = StockReservation::create(array_merge([
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'qty_base' => $qtyBase,
                'level' => $level,
                'status' => ReservationStatus::Active,
            ], $attributes));

            activity('stock')
                ->performedOn($reservasi)
                ->causedBy($actor)
                ->withProperties(['level' => $level->value, 'qty' => $qtyBase])
                ->log('Reservasi dibuat');

            return $reservasi;
        });
    }
}
