<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Stock\Models\StockBalance;

/**
 * Rekonsiliasi saldo terjadwal (BR-STK-01, A-243): saldo tersimpan
 * `stock_balances` dibandingkan dengan hasil penjumlahan kartu stok
 * (`StockLedger::rebuildFromLedger`). Kartu stok adalah kebenaran (P-01),
 * jadi selisih **hanya dilaporkan**, tidak pernah diperbaiki otomatis —
 * perbaikannya lewat penyelidikan dan ADJ.
 */
class StockReconciler
{
    private const TOLERANSI = 0.00005;

    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @return list<array{key: string, item_id: int, bin_id: int, lot_id: int|null, serial_id: int|null, piece_id: int|null, stock_status: string, ledger: float, balance: float, difference: float}>
     */
    public function differences(): array
    {
        $ledger = $this->ledger->rebuildFromLedger();
        $saldo = [];

        StockBalance::query()->orderBy('id')->chunk(1000, function ($baris) use (&$saldo): void {
            foreach ($baris as $b) {
                $kunci = implode('|', [$b->item_id, $b->bin_id, $b->lot_id ?? 0, $b->serial_id ?? 0, $b->piece_id ?? 0, $b->stock_status->value]);
                $saldo[$kunci] = ($saldo[$kunci] ?? 0) + round((float) $b->qty_base, 4);
            }
        });

        $hasil = [];

        foreach (array_unique([...array_keys($ledger), ...array_keys($saldo)]) as $kunci) {
            $dariKartu = round($ledger[$kunci] ?? 0, 4);
            $tersimpan = round($saldo[$kunci] ?? 0, 4);

            if (abs($dariKartu - $tersimpan) <= self::TOLERANSI) {
                continue;
            }

            [$item, $bin, $lot, $serial, $piece, $status] = explode('|', (string) $kunci);
            $hasil[] = [
                'key' => (string) $kunci,
                'item_id' => (int) $item,
                'bin_id' => (int) $bin,
                'lot_id' => (int) $lot ?: null,
                'serial_id' => (int) $serial ?: null,
                'piece_id' => (int) $piece ?: null,
                'stock_status' => $status,
                'ledger' => $dariKartu,
                'balance' => $tersimpan,
                'difference' => round($tersimpan - $dariKartu, 4),
            ];
        }

        return $hasil;
    }
}
