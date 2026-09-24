<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu stok boleh berubah (P-01, BR-STK-01, AD-04).
 *
 * Seluruh modul dokumen memanggil `post()`; tidak ada satu pun yang menulis
 * `stock_balances` sendiri. Setiap panggilan berjalan dalam satu transaksi:
 * saldo dikunci baris dengan urutan tetap, kartu stok ditulis, kejadian stok
 * masuk outbox, dan bila salah satu gagal semuanya dibatalkan (BR-LED-06).
 */
class StockLedger
{
    /** Peringatan kapasitas yang muncul pada posting terakhir (BR-STK-07). */
    private array $warnings = [];

    /**
     * Memposting satu pergerakan.
     *
     * @throws LedgerException bila melanggar aturan stok
     */
    public function post(MovementRequest $request): StockMovement
    {
        $this->validate($request);

        return DB::transaction(function () use ($request): StockMovement {
            $this->warnings = [];

            $waktu = $request->occurredAt?->copy()->utc() ?? now()->utc();

            // Urutan tetap: keluar dulu, baru masuk. Dengan begitu dua proses
            // yang memindahkan barang berlawanan arah tidak saling mengunci.
            if ($request->fromBinId !== null) {
                $this->kurangi($request);
            }

            if ($request->toBinId !== null) {
                $this->tambah($request);
            }

            $movement = StockMovement::create([
                'item_id' => $request->item->id,
                'from_bin_id' => $request->fromBinId,
                'to_bin_id' => $request->toBinId,
                'lot_id' => $request->lotId,
                'serial_id' => $request->serialId,
                'piece_id' => $request->pieceId,
                'qty_base' => $request->qtyBase,
                'stock_status' => $request->stockStatus,
                'project_id' => $request->projectId,
                'document_type' => $request->documentType,
                'document_id' => $request->documentId,
                'document_line_id' => $request->documentLineId,
                'document_number' => $request->documentNumber,
                'reason_code_id' => $request->reasonCodeId,
                'occurred_at' => $waktu,
                'performed_by' => $request->performedBy?->id,
                'reverses_movement_id' => $request->reversesMovementId,
                'notes' => $request->notes,
            ]);

            if ($request->eventType !== null) {
                $this->emit($movement, $request);
            }

            return $movement;
        });
    }

    /**
     * BR-LED-05 — membalik pergerakan: asal dan tujuan ditukar, sekali saja.
     *
     * Dokumen pembalik yang harus menerbitkan kejadian (matriks §14 "Kejadian
     * pembalik": jenis sama dengan asal + `reverses_event_id`) menyebut jenis
     * dan payload-nya; tanpa itu pembalikan tidak menerbitkan kejadian.
     *
     * @param  array<string, mixed>  $eventPayload
     */
    public function reverse(
        StockMovement $movement,
        ?int $reasonCodeId = null,
        ?string $notes = null,
        ?StockEventType $eventType = null,
        array $eventPayload = [],
        ?string $documentType = null,
        ?int $documentId = null,
        ?int $documentLineId = null,
        ?string $documentNumber = null,
    ): StockMovement {
        if ($movement->hasBeenReversed()) {
            throw LedgerException::rule('BR-LED-05', 'Pergerakan ini sudah pernah dibalik.');
        }

        if ($movement->reverses_movement_id !== null) {
            throw LedgerException::rule('BR-LED-05', 'Baris pembalik tidak bisa dibalik lagi.');
        }

        $this->assertPeriodOpen(now()->utc());

        $pembalik = $this->post(new MovementRequest(
            item: $movement->item,
            qtyBase: (float) $movement->qty_base,
            // Ditukar: yang tadinya keluar sekarang masuk.
            fromBinId: $movement->to_bin_id,
            toBinId: $movement->from_bin_id,
            stockStatus: $movement->stock_status,
            lotId: $movement->lot_id,
            serialId: $movement->serial_id,
            pieceId: $movement->piece_id,
            projectId: $movement->project_id,
            documentType: $documentType ?? $movement->document_type,
            documentId: $documentId ?? $movement->document_id,
            documentLineId: $documentLineId ?? $movement->document_line_id,
            documentNumber: $documentNumber ?? $movement->document_number,
            reasonCodeId: $reasonCodeId ?? $movement->reason_code_id,
            performedBy: auth()->user(),
            eventType: $eventType,
            eventPayload: $eventPayload,
            notes: $notes,
            // Ikut saat insert: baris kartu stok tidak pernah di-update (P-01).
            reversesMovementId: (int) $movement->id,
        ));

        return $pembalik;
    }

    /** @return array<int, string> peringatan kapasitas dari posting terakhir */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Stok tersedia = saldo Tersedia di bin penyimpanan dikurangi reservasi aktif (BR-STK-03).
     *
     * Hanya bin `storage` yang dihitung — sama dengan sumber alokasi picking (A-84).
     * Barang di Penerimaan belum di-put-away, di Staging sudah milik tugas picking,
     * dan di Dalam Perjalanan sedang di truk; tak satu pun boleh dijanjikan ke REQ (A-85).
     */
    public function availableQty(int $itemId, int $warehouseId): float
    {
        $saldo = (float) StockBalance::query()
            ->available()
            ->where('item_id', $itemId)
            ->whereHas('bin', fn ($q) => $q->where('warehouse_id', $warehouseId)
                ->where('bin_type', \App\Domain\Warehouse\Enums\BinType::Storage->value))
            ->sum('qty_base');

        $reservasi = (float) \App\Domain\Stock\Models\StockReservation::query()
            ->active()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('qty_base');

        return round($saldo - $reservasi, 4);
    }

    /**
     * Membangun ulang saldo dari kartu stok (BR-STK-01).
     *
     * Dipakai laporan rekonsiliasi dan uji: saldo harus selalu sama dengan
     * hasil penjumlahan ledger, kalau tidak ada yang salah.
     *
     * @return array<string, float> kunci gabungan => jumlah
     */
    public function rebuildFromLedger(?int $itemId = null): array
    {
        $hasil = [];

        StockMovement::query()
            ->when($itemId !== null, fn ($q) => $q->where('item_id', $itemId))
            ->orderBy('id')
            ->chunk(500, function ($baris) use (&$hasil): void {
                foreach ($baris as $movement) {
                    if ($movement->from_bin_id !== null) {
                        $kunci = $this->kunci($movement, (int) $movement->from_bin_id);
                        $hasil[$kunci] = ($hasil[$kunci] ?? 0) - (float) $movement->qty_base;
                    }

                    if ($movement->to_bin_id !== null) {
                        $kunci = $this->kunci($movement, (int) $movement->to_bin_id);
                        $hasil[$kunci] = ($hasil[$kunci] ?? 0) + (float) $movement->qty_base;
                    }
                }
            });

        return array_map(fn (float $n) => round($n, 4), $hasil);
    }

    // ---------------------------------------------------------------- privat

    private function validate(MovementRequest $request): void
    {
        // BR-LED-01
        if ($request->fromBinId === null && $request->toBinId === null) {
            throw LedgerException::rule(
                'BR-LED-01',
                'Pergerakan wajib punya bin asal, bin tujuan, atau keduanya.',
            );
        }

        // BR-LED-02
        if ($request->qtyBase <= 0) {
            throw LedgerException::rule(
                'BR-LED-02',
                'Jumlah pergerakan harus lebih besar dari nol; arah ditentukan bin asal dan tujuan.',
            );
        }

        // Bin asal = bin tujuan hanya sah bila yang berubah kondisinya: barang
        // tidak pindah, tetapi statusnya berganti (mis. QC menolak, atau barang
        // kembali dari kirim dalam keadaan rusak).
        if ($request->fromBinId !== null
            && $request->fromBinId === $request->toBinId
            && ! $request->isConditionChange()) {
            throw LedgerException::rule(
                'BR-LED-01',
                'Bin asal dan tujuan tidak boleh sama kecuali kondisi stoknya berubah.',
            );
        }

        $this->assertTrackingProvided($request);
        $this->assertSerialSingleLocation($request);
        $this->assertPeriodOpen($request->occurredAt?->copy()->utc() ?? now()->utc());
        $this->assertBinsUsable($request);
    }

    /** BR-LED-03: item berpelacakan wajib menyebut turunannya. */
    private function assertTrackingProvided(MovementRequest $request): void
    {
        [$nilai, $label] = match ($request->item->tracking_mode) {
            TrackingMode::Lot => [$request->lotId, 'Nomor lot'],
            TrackingMode::Serial => [$request->serialId, 'Serial'],
            TrackingMode::Piece => [$request->pieceId, 'Potongan'],
            TrackingMode::None => [null, null],
        };

        if ($label !== null && $nilai === null) {
            throw LedgerException::rule(
                'BR-LED-03',
                $label.' wajib disebut untuk item bermode '.$request->item->tracking_mode->label().'.',
            );
        }

        // BR-STK-08: aset selalu bergerak per serial.
        if ($request->item->isAsset() && $request->serialId === null) {
            throw LedgerException::rule('BR-STK-08', 'Pergerakan aset wajib menyebut serial.');
        }
    }

    /** BR-STK-15: periode yang sudah dikunci menolak mutasi apa pun. */
    private function assertPeriodOpen(\Carbon\CarbonInterface $occurredAt): void
    {
        $kunci = CompanySetting::get('stock_lock_date');

        if (! is_string($kunci) || $kunci === '') {
            return;
        }

        if ($occurredAt->toDateString() <= $kunci) {
            throw LedgerException::rule(
                'BR-STK-15',
                'Periode stok sampai '.$kunci.' sudah dikunci. Posting koreksi di periode berjalan.',
            );
        }
    }

    /** Bin beku menolak pergerakan (BR-OPN-02); bin on-site hanya untuk aset (BR-STK-14). */
    private function assertBinsUsable(MovementRequest $request): void
    {
        foreach ([$request->fromBinId, $request->toBinId] as $binId) {
            if ($binId === null) {
                continue;
            }

            $bin = Bin::withoutGlobalScopes()->find($binId);

            if ($bin === null) {
                throw LedgerException::rule('BR-STK-02', 'Bin tidak ditemukan.');
            }

            if (! $bin->acceptsMovement()) {
                throw LedgerException::rule(
                    'BR-OPN-02',
                    'Bin '.$bin->code.' berstatus '.$bin->bin_status->label().' dan menolak pergerakan baru.',
                );
            }

            if ($bin->bin_type === BinType::OnSite && ! $request->item->isAsset()) {
                throw LedgerException::rule(
                    'BR-STK-14',
                    'Bin On-site Proyek hanya menampung aset; barang habis pakai tidak pernah berada di sana.',
                );
            }
        }
    }

    /** Mengurangi saldo bin asal; menolak bila tidak cukup (BR-STK-06). */
    private function kurangi(MovementRequest $request): void
    {
        $saldo = $this->lockBalance($request, (int) $request->fromBinId, $request->sourceStatus());

        $sesudah = round((float) $saldo->qty_base - $request->qtyBase, 4);

        if ($sesudah < 0) {
            $bin = Bin::withoutGlobalScopes()->find($request->fromBinId);

            throw LedgerException::rule(
                'BR-STK-06',
                'Saldo bin '.($bin?->code ?? $request->fromBinId).' tidak cukup: tersedia '
                .rtrim(rtrim(number_format((float) $saldo->qty_base, 4, '.', ''), '0'), '.')
                .', diminta '.rtrim(rtrim(number_format($request->qtyBase, 4, '.', ''), '0'), '.').'.',
            );
        }

        $saldo->forceFill([
            'qty_base' => $sesudah,
            'piece_count' => $request->pieceId !== null ? max(0, $saldo->piece_count - 1) : $saldo->piece_count,
            'version' => $saldo->version + 1,
        ])->save();
    }

    /** Menambah saldo bin tujuan; memeriksa kapasitas (BR-WH-06). */
    private function tambah(MovementRequest $request): void
    {
        $bin = Bin::withoutGlobalScopes()->with('storageCategory')->find($request->toBinId);
        $saldo = $this->lockBalance($request, (int) $request->toBinId, $request->stockStatus);

        $sesudah = round((float) $saldo->qty_base + $request->qtyBase, 4);

        if ($bin !== null && $bin->exceedsCapacity($sesudah)) {
            if ($bin->blocksOnOverCapacity()) {
                throw LedgerException::rule(
                    'BR-WH-06',
                    'Kapasitas bin '.$bin->code.' terlampaui dan kategori penyimpanannya menolak penempatan.',
                );
            }

            $this->warnings[] = 'Kapasitas bin '.$bin->code.' terlampaui.';
        }

        $saldo->forceFill([
            'qty_base' => $sesudah,
            'piece_count' => $request->pieceId !== null ? $saldo->piece_count + 1 : $saldo->piece_count,
            'version' => $saldo->version + 1,
        ])->save();
    }

    /**
     * Mengunci baris saldo dengan urutan tetap (AD-04).
     *
     * Baris yang belum ada dibuat lebih dulu, lalu dikunci ulang, supaya dua
     * proses yang menyentuh kombinasi sama tidak membuat dua baris.
     */
    private function lockBalance(MovementRequest $request, int $binId, StockStatus $status): StockBalance
    {
        $kunci = [
            'item_id' => $request->item->id,
            'bin_id' => $binId,
            'lot_id' => $request->lotId,
            'serial_id' => $request->serialId,
            'piece_id' => $request->pieceId,
            'stock_status' => $status->value,
        ];

        $cari = fn () => StockBalance::query()
            ->where('item_id', $kunci['item_id'])
            ->where('bin_id', $kunci['bin_id'])
            ->where(fn ($q) => $kunci['lot_id'] === null ? $q->whereNull('lot_id') : $q->where('lot_id', $kunci['lot_id']))
            ->where(fn ($q) => $kunci['serial_id'] === null ? $q->whereNull('serial_id') : $q->where('serial_id', $kunci['serial_id']))
            ->where(fn ($q) => $kunci['piece_id'] === null ? $q->whereNull('piece_id') : $q->where('piece_id', $kunci['piece_id']))
            ->where('stock_status', $kunci['stock_status'])
            ->lockForUpdate()
            ->first();

        $saldo = $cari();

        if ($saldo !== null) {
            return $saldo;
        }

        StockBalance::create($kunci + ['qty_base' => 0, 'piece_count' => 0, 'version' => 0]);

        return $cari();
    }

    /**
     * Kejadian tanpa pergerakan stok.
     *
     * Sebagian kejadian mengumumkan sesuatu yang benar tanpa memindahkan
     * apa pun — misalnya `stock_transferred` saat barang diterima gudang
     * tujuan tetapi masih tercatat milik gudang asal sampai GRN-nya dibuat
     * (BR-SJ-04). Tetap lewat sini supaya outbox menjadi satu-satunya tempat
     * kejadian lahir (AD-05).
     *
     * @param  array<string, mixed>  $payload
     */
    public function emitEvent(
        StockEventType $type,
        array $payload,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $sourceNumber = null,
        ?int $projectId = null,
    ): StockEvent {
        return StockEvent::create([
            'event_id' => (string) Str::uuid(),
            'schema_version' => '1.0',
            'event_type' => $type,
            'occurred_at' => now()->utc(),
            'recorded_at' => now()->utc(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_number' => $sourceNumber,
            'project_id' => $projectId,
            'payload' => array_merge([
                'timezone' => tenant()?->timezone ?? 'Asia/Jakarta',
            ], $payload),
        ]);
    }

    /** BR-LED-06: kejadian masuk outbox dalam transaksi yang sama. */
    private function emit(StockMovement $movement, MovementRequest $request): void
    {
        StockEvent::create([
            'event_id' => (string) Str::uuid(),
            'schema_version' => '1.0',
            'event_type' => $request->eventType,
            'occurred_at' => $movement->occurred_at,
            'recorded_at' => now()->utc(),
            'source_type' => $request->documentType,
            'source_id' => $request->documentId,
            'source_number' => $request->documentNumber,
            'project_id' => $request->projectId,
            // Kejadian pembalik menunjuk kejadian asalnya (BR-GEN-03, matriks §14).
            'reverses_event_id' => $request->eventPayload['reverses_event_id'] ?? null,
            'payload' => array_merge([
                'movement_id' => $movement->id,
                'item_id' => $request->item->id,
                'item_code' => $request->item->code,
                'base_uom' => $request->item->baseUom?->code,
                'qty_base' => (float) $request->qtyBase,
                'stock_status' => $request->stockStatus->value,
                'from_bin_id' => $request->fromBinId,
                'to_bin_id' => $request->toBinId,
                'lot_id' => $request->lotId,
                'serial_id' => $request->serialId,
                'piece_id' => $request->pieceId,
                'timezone' => tenant()?->timezone ?? 'Asia/Jakarta',
            ], $request->eventPayload),
        ]);
    }

    /**
     * BR-LED-04 — satu serial hanya boleh berada di satu bin pada satu waktu.
     *
     * Memasukkan serial ke bin baru hanya sah bila ia keluar dari bin lamanya
     * dalam pergerakan yang sama, atau memang belum ada di mana pun.
     */
    private function assertSerialSingleLocation(MovementRequest $request): void
    {
        if ($request->serialId === null || $request->toBinId === null) {
            return;
        }

        $lokasiLain = StockBalance::query()
            ->where('serial_id', $request->serialId)
            ->where('qty_base', '>', 0)
            // Bin asal dikecualikan: keluar dari sana memang bagian dari pergerakan ini.
            ->when($request->fromBinId !== null, fn ($q) => $q->where('bin_id', '!=', $request->fromBinId))
            ->first();

        if ($lokasiLain !== null) {
            $bin = Bin::withoutGlobalScopes()->find($lokasiLain->bin_id);

            throw LedgerException::rule(
                'BR-LED-04',
                'Serial ini masih tercatat di bin '.($bin?->code ?? $lokasiLain->bin_id).'; keluarkan dulu dari sana.',
            );
        }
    }

    private function kunci(StockMovement $movement, int $binId): string
    {
        return implode('|', [
            $movement->item_id,
            $binId,
            $movement->lot_id ?? 0,
            $movement->serial_id ?? 0,
            $movement->piece_id ?? 0,
            $movement->stock_status->value,
        ]);
    }
}
