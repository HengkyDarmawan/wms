<?php

declare(strict_types=1);

namespace App\Domain\Stock\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Notification\Support\DomainNotifications;
use App\Domain\Stock\Exceptions\LedgerException;

/**
 * Permission: `stock.lock_period`.
 *
 * BR-STK-15 — tanggal kunci periode stok. Setelah dikunci, mutasi apa pun
 * dengan `occurred_at` pada atau sebelum tanggal itu ditolak, termasuk dokumen
 * pembalik; koreksi diposting di periode berjalan.
 *
 * Tanggal kunci hanya boleh **maju**. Memundurkannya akan membuka kembali
 * periode yang laporannya sudah dipakai, jadi ditolak.
 */
class LockStockPeriod
{
    public const KUNCI = 'stock_lock_date';

    public function handle(string $date, ?string $notes = null, ?User $actor = null): string
    {
        $tanggal = $this->parse($date);
        $sekarang = $this->current();

        if ($sekarang !== null && $tanggal <= $sekarang) {
            throw LedgerException::rule(
                'BR-STK-15',
                'Tanggal kunci hanya bisa dimajukan. Sekarang terkunci sampai '.$sekarang.'.',
            );
        }

        if ($tanggal > now()->toDateString()) {
            throw LedgerException::rule(
                'BR-STK-15',
                'Tanggal kunci tidak boleh melewati hari ini.',
            );
        }

        CompanySetting::put(self::KUNCI, $tanggal);

        activity('stock')
            ->causedBy($actor)
            ->withProperties(['dari' => $sekarang, 'ke' => $tanggal, 'notes' => $notes])
            ->log('Periode stok dikunci sampai '.$tanggal);

        app(DomainNotifications::class)->stockPeriodLocked($tanggal, $actor);

        return $tanggal;
    }

    public function current(): ?string
    {
        $nilai = CompanySetting::get(self::KUNCI);

        return is_string($nilai) && $nilai !== '' ? $nilai : null;
    }

    private function parse(string $date): string
    {
        $tanggal = trim($date);

        if ($tanggal === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            throw LedgerException::rule('BR-GEN-11', 'Tanggal kunci wajib diisi dalam format YYYY-MM-DD.');
        }

        return $tanggal;
    }
}
