<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Stok awal & kendaraan demo company DEMO — sumber kebenaran: docs/00-akun-uji.md §2.
 * HANYA untuk dev, demo, dan staging (A-72).
 *
 * Modul Receipt dan Adjustment belum ada, jadi tanpa seeder ini tidak ada jalan
 * memasukkan stok dari layar dan alur REQ → PCK → SJ tidak bisa dicoba manual.
 * Stok tetap masuk lewat StockLedger (P-01): tercatat di kartu stok, bukan
 * menulis saldo langsung.
 */
class StockDemoSeeder extends Seeder
{
    public const NOTES = 'Stok awal demo (seeder dev)';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('StockDemoSeeder tidak boleh dijalankan di produksi.');
        }

        $this->seedVehicles();

        // Kartu stok append-only: menjalankan ulang seeder tidak boleh menggandakan saldo.
        if (StockMovement::query()->where('notes', self::NOTES)->exists()) {
            $this->command?->info('Stok demo sudah ada; dilewati.');

            return;
        }

        $item = Item::query()->pluck('id', 'code')->all();

        foreach (['BAUT-M12', 'SEMEN-PCC-50', 'PIPA-PVC-4', 'GENSET-5KVA'] as $kode) {
            if (! isset($item[$kode])) {
                throw new RuntimeException('Item '.$kode.' belum ada. Jalankan MasterDemoSeeder lebih dulu.');
            }
        }

        $this->seedUntracked('BAUT-M12', ['CKG-A-R01-L1-B01' => 1000, 'BKS-A-R01-L1-B01' => 300]);

        $this->seedLots('SEMEN-PCC-50', [
            ['LOT-SMN-2609', 6, 'CKG-A-R01-L1-B02', 2000],
            ['LOT-SMN-2610', 9, 'CKG-A-R01-L1-B02', 1500],
            ['LOT-SMN-2610', 9, 'BKS-A-R01-L1-B02', 500],
        ]);

        // Sepuluh batang utuh dan dua sisa potongan di CKG, empat batang di BKS.
        $this->seedPieces('PIPA-PVC-4', [
            'CKG-B-R01-L1-B01' => [6, 6, 6, 6, 6, 6, 6, 6, 6, 6, 2.5, 1.2],
            'BKS-A-R01-L2-B01' => [6, 6, 6, 6],
        ]);

        $this->seedSerials('GENSET-5KVA', [
            'GNS-5K-0001' => 'CKG-B-R01-L2-B01',
            'GNS-5K-0002' => 'CKG-B-R01-L2-B01',
        ]);

        $this->command?->info('Stok demo siap: '.StockMovement::query()->where('notes', self::NOTES)->count().' baris kartu stok.');
    }

    /** Kendaraan kurir internal milik sendiri (A-57), sesuai kolom Catatan driver. */
    private function seedVehicles(): void
    {
        foreach ([
            'B 9001 XX' => 'driver1@demo.wms.test',
            'B 9002 XX' => 'driver2@demo.wms.test',
        ] as $plat => $email) {
            Vehicle::updateOrCreate(
                ['plate_no' => $plat],
                [
                    'type' => 'Pikap',
                    'default_driver_id' => User::query()->where('email', $email)->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }

    /** @param  array<string, float|int>  $perBin */
    private function seedUntracked(string $kodeItem, array $perBin): void
    {
        $item = $this->item($kodeItem);

        foreach ($perBin as $kodeBin => $qty) {
            $this->post($item, (float) $qty, $kodeBin);
        }
    }

    /** @param  array<int, array{0: string, 1: int, 2: string, 3: float|int}>  $baris  [lot, bulan kedaluwarsa, bin, qty] */
    private function seedLots(string $kodeItem, array $baris): void
    {
        $item = $this->item($kodeItem);

        foreach ($baris as [$noLot, $bulan, $kodeBin, $qty]) {
            $lot = Lot::firstOrCreate(
                ['item_id' => $item->id, 'lot_no' => $noLot],
                ['expiry_date' => now()->addMonths($bulan)->toDateString(), 'received_at' => now()->toDateString()],
            );

            $this->post($item, (float) $qty, $kodeBin, lotId: $lot->id);
        }
    }

    /** @param  array<string, array<int, float|int>>  $perBin  panjang tiap potongan dalam meter */
    private function seedPieces(string $kodeItem, array $perBin): void
    {
        $item = $this->item($kodeItem);
        $nomor = Piece::query()->count();

        foreach ($perBin as $kodeBin => $panjangList) {
            foreach ($panjangList as $panjang) {
                $potongan = Piece::create([
                    'item_id' => $item->id,
                    'piece_no' => sprintf('P-%06d', ++$nomor),
                    'length' => $panjang,
                    'is_offcut' => $panjang < 6,
                ]);

                $this->post($item, (float) $panjang, $kodeBin, pieceId: $potongan->id);
            }
        }
    }

    /** @param  array<string, string>  $serialKeBin */
    private function seedSerials(string $kodeItem, array $serialKeBin): void
    {
        $item = $this->item($kodeItem);

        foreach ($serialKeBin as $noSerial => $kodeBin) {
            $serial = Serial::firstOrCreate(
                ['item_id' => $item->id, 'serial_no' => $noSerial],
                ['acquired_at' => now()->toDateString()],
            );

            $this->post($item, 1.0, $kodeBin, serialId: $serial->id);
        }
    }

    private function post(Item $item, float $qty, string $kodeBin, ?int $lotId = null, ?int $serialId = null, ?int $pieceId = null): void
    {
        app(StockLedger::class)->post(new MovementRequest(
            item: $item,
            qtyBase: $qty,
            toBinId: $this->binId($kodeBin),
            lotId: $lotId,
            serialId: $serialId,
            pieceId: $pieceId,
            reasonCodeId: $this->reasonId(),
            eventType: StockEventType::StockAdjusted,
            notes: self::NOTES,
        ));
    }

    private function item(string $kode): Item
    {
        return Item::query()->where('code', $kode)->firstOrFail();
    }

    private function binId(string $kode): int
    {
        return (int) (Bin::withoutGlobalScopes()->where('code', $kode)->value('id')
            ?? throw new RuntimeException('Bin '.$kode.' belum ada. Jalankan WarehouseDemoSeeder lebih dulu.'));
    }

    private function reasonId(): ?int
    {
        return ReasonCode::query()
            ->where('context', ReasonContext::Adjustment)
            ->where('code', 'OTHER')
            ->value('id');
    }
}
