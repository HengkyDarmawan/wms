<?php

declare(strict_types=1);

namespace App\Domain\Request\Support;

use App\Domain\Access\Models\User;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Models\ShipmentLine;

/**
 * Jejak pemenuhan REQ dari dokumen keluar (Katalog Status §2.1).
 *
 * Satu-satunya tempat yang menulis `qty_shipped` dan `qty_received` pada baris
 * REQ dan menurunkan status REQ dari jumlah itu. Dipanggil saat surat jalan
 * berangkat, saat bukti terima diisi, dan saat DSC menutup sisa baris.
 *
 * Tanpa jejak ini REQ yang barangnya sudah di site masih bisa dibatalkan
 * (BR-REQ-09) dan tidak pernah selesai.
 */
class RequestFulfillment
{
    private const EPS = 0.00005;

    /** Surat jalan berangkat: jumlah dikirim bertambah, janji reservasi berkurang. */
    public function shipped(ShipmentLine $line): void
    {
        $baris = $this->sumber($line);

        if ($baris === null) {
            return;
        }

        $qty = (float) $line->qty_shipped;

        $baris->forceFill([
            'qty_shipped' => (float) $baris->qty_shipped + $qty,
            'qty_reserved' => max(0, (float) $baris->qty_reserved - $qty),
        ])->save();
    }

    /** Bukti terima: hanya jumlah **baik** yang dihitung diterima (BR-SJ-06). */
    public function received(ShipmentLine $line, float $qtyGood, ?User $actor = null): void
    {
        $baris = $this->sumber($line);

        if ($baris === null) {
            return;
        }

        $baris->forceFill(['qty_received' => (float) $baris->qty_received + $qtyGood])->save();

        $this->refresh($baris->request, $actor);
    }

    /**
     * Menurunkan status baris dan dokumen dari jumlah yang tercatat.
     *
     * - baris `open` yang sudah diterima penuh → `closed`;
     * - semua baris aktif `closed` → REQ `completed` (konfirmasi pemohon BR-REQ-10
     *   belum dibangun, jadi bukti terima dianggap cukup — A-77);
     * - ada yang sudah diterima tetapi belum semua → `partially_fulfilled`.
     */
    public function refresh(?MaterialRequest $request, ?User $actor = null): void
    {
        if ($request === null) {
            return;
        }

        $mengalir = [MaterialRequestStatus::InProgress, MaterialRequestStatus::PartiallyFulfilled];

        if (! in_array($request->status, $mengalir, true)) {
            return;
        }

        $lines = $request->lines()->get();

        foreach ($lines as $l) {
            if ($l->status === RequestLineStatus::Open
                && (float) $l->qty_received + self::EPS >= (float) $l->qty_base) {
                $l->forceFill(['status' => RequestLineStatus::Closed, 'qty_backorder' => 0])->save();
            }
        }

        $aktif = $lines->reject(fn (MaterialRequestLine $l) => $l->status === RequestLineStatus::Cancelled);
        $semuaTutup = $aktif->isNotEmpty()
            && $aktif->every(fn (MaterialRequestLine $l) => $l->status === RequestLineStatus::Closed);
        $adaDiterima = $aktif->contains(fn (MaterialRequestLine $l) => (float) $l->qty_received > 0);

        $baru = match (true) {
            $semuaTutup => MaterialRequestStatus::Completed,
            $adaDiterima => MaterialRequestStatus::PartiallyFulfilled,
            default => $request->status,
        };

        if ($baru === $request->status) {
            return;
        }

        $lama = $request->status;
        $request->forceFill(['status' => $baru])->save();

        activity('request')
            ->performedOn($request)
            ->causedBy($actor)
            ->withProperties(['dari' => $lama->value, 'ke' => $baru->value])
            ->log($baru === MaterialRequestStatus::Completed ? 'REQ selesai' : 'REQ terpenuhi sebagian');
    }

    private function sumber(ShipmentLine $line): ?MaterialRequestLine
    {
        $asal = $line->pickTaskLine;

        if ($asal === null || $asal->pickTask?->source_type !== 'material_request') {
            return null;
        }

        return MaterialRequestLine::query()->with('request')->find($asal->source_line_id);
    }
}
