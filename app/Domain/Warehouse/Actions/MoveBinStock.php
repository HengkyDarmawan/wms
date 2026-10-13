<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\StoragePolicy;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `putaway.complete` — **Pindah bin** (A-416): memindah stok dari
 * satu bin penyimpanan ke bin penyimpanan lain di gudang yang sama, tanpa
 * dokumen dan tanpa approval (jumlah stok gudang tidak berubah, hanya
 * letaknya). Satu pergerakan lewat StockLedger (P-01) ber-`document_type`
 * `bin_move`; label kemasan barang itu di bin asal ikut pindah, utuh per label.
 *
 * Ditolak: bin bukan penyimpanan aktif, bin tergabung (pakai bin utamanya),
 * bin asal = tujuan, jumlah melebihi saldo dikurangi reservasi yang menyebut
 * bin asal, serial bukan 1 unit, potongan tidak utuh, dan bin tujuan *Khusus*
 * barang lain (BR-WH-10). Bin beku opname & kapasitas dijaga StockLedger.
 */
class MoveBinStock
{
    private const EPS = 0.00005;

    /** @var array<int, string> */
    private array $peringatan = [];

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly StoragePolicy $khusus,
        private readonly PackageLabelLedger $label,
    ) {}

    /**
     * @param  array{balance_id?: int|string, to_bin_id?: int|string, qty?: int|float|string, notes?: ?string}  $data
     */
    public function handle(Warehouse $gudang, array $data, User $actor): StockMovement
    {
        if (! $actor->hasPermission('putaway.complete') || ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw WarehouseRuleException::rule('BR-GEN-09', 'Anda tidak berwenang memindah barang di gudang ini.');
        }

        $saldo = StockBalance::query()->withoutGlobalScopes()->with('item', 'bin')->find((int) ($data['balance_id'] ?? 0));

        if ($saldo === null || $saldo->bin === null || (int) $saldo->bin->warehouse_id !== (int) $gudang->id || (float) $saldo->qty_base <= self::EPS) {
            throw WarehouseRuleException::fields(['balance_id' => 'Pilih barang di bin asal.'], 'BR-STK-02');
        }

        $asal = $saldo->bin;
        $tujuan = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->find((int) ($data['to_bin_id'] ?? 0));

        if ($tujuan === null) {
            throw WarehouseRuleException::fields(['to_bin_id' => 'Pilih bin tujuan di gudang ini.'], 'BR-STK-02');
        }

        $this->pastikanBin($asal, 'balance_id', 'asal');
        $this->pastikanBin($tujuan, 'to_bin_id', 'tujuan');

        if ((int) $asal->id === (int) $tujuan->id) {
            throw WarehouseRuleException::fields(['to_bin_id' => 'Bin tujuan harus berbeda dari bin asal.'], 'BR-STK-02');
        }

        $item = $saldo->item;
        $qty = $this->jumlah($saldo, $data['qty'] ?? null);

        // Reservasi yang sudah menunjuk bin asal (mis. alokasi picking) tidak boleh ikut dipindah.
        $terikat = (float) StockReservation::query()->active()->where('item_id', $item->id)->where('bin_id', $asal->id)->sum('qty_base');
        $bebas = round((float) $saldo->qty_base - $terikat, 4);

        if ($qty - $bebas > self::EPS) {
            throw WarehouseRuleException::fields(['qty' => 'Paling banyak '.$this->angka(max(0, $bebas)).' yang bisa dipindah (sisanya sudah direservasi di bin ini).'], 'BR-STK-03');
        }

        if (($tolak = $this->khusus->tolakan($tujuan, $item)) !== null) {
            throw WarehouseRuleException::fields(['to_bin_id' => $tolak], 'BR-WH-10');
        }

        $catatan = trim((string) ($data['notes'] ?? ''));

        return DB::transaction(function () use ($saldo, $asal, $tujuan, $item, $qty, $catatan, $actor) {
            try {
                $gerak = $this->ledger->post(new MovementRequest(
                    item: $item,
                    qtyBase: $qty,
                    fromBinId: (int) $asal->id,
                    toBinId: (int) $tujuan->id,
                    stockStatus: $saldo->stock_status,
                    lotId: $saldo->lot_id,
                    serialId: $saldo->serial_id,
                    pieceId: $saldo->piece_id,
                    documentType: 'bin_move',
                    performedBy: $actor,
                    notes: mb_substr('Pindah bin'.($catatan !== '' ? ': '.$catatan : ''), 0, 255),
                ));
            } catch (LedgerException $e) {
                throw new WarehouseRuleException($e->getMessage(), $e->rule);
            }

            $this->peringatan = $this->ledger->warnings();

            // A-296: label kemasan barang ini di bin asal ikut pindah, utuh per label.
            $this->label->moveBin((int) $item->id, $saldo->lot_id !== null ? (int) $saldo->lot_id : null, (int) $asal->id, (int) $tujuan->id, $qty,
                ['type' => 'bin_move', 'id' => (int) $gerak->id, 'line_id' => null, 'number' => null], $actor);

            activity('stock')->performedOn($gerak)->causedBy($actor)
                ->withProperties(['item' => $item->code, 'dari' => $asal->code, 'ke' => $tujuan->code, 'jumlah' => $qty])
                ->log('Pindah bin '.$asal->code.' → '.$tujuan->code);

            return $gerak;
        });
    }

    /** @return array<int, string> peringatan kapasitas dari pemindahan terakhir */
    public function warnings(): array
    {
        return $this->peringatan;
    }

    private function pastikanBin(Bin $bin, string $field, string $peran): void
    {
        if ($bin->bin_type !== BinType::Storage || $bin->bin_status !== BinStatus::Active) {
            throw WarehouseRuleException::fields([$field => 'Bin '.$peran.' '.$bin->code.' bukan bin penyimpanan aktif.'], 'BR-STK-02');
        }

        if ($bin->occupied_by_bin_id !== null) {
            $utama = Bin::query()->withoutGlobalScopes()->whereKey($bin->occupied_by_bin_id)->value('code');

            throw WarehouseRuleException::fields([$field => 'Bin '.$bin->code.' tergabung ke '.$utama.'; pakai bin utamanya.'], 'BR-WH-08');
        }
    }

    private function jumlah(StockBalance $saldo, mixed $isian): float
    {
        $mode = $saldo->item->tracking_mode;

        // Serial & potongan selalu pindah utuh: satu unit / satu potongan.
        if ($mode === TrackingMode::Serial || $mode === TrackingMode::Piece || $saldo->piece_id !== null) {
            return round((float) $saldo->qty_base, 4);
        }

        $qty = is_numeric(str_replace(',', '.', (string) $isian)) ? round((float) str_replace(',', '.', (string) $isian), 4) : 0.0;

        if ($qty <= self::EPS) {
            throw WarehouseRuleException::fields(['qty' => 'Jumlah harus lebih dari 0.'], 'BR-LED-02');
        }

        if ($qty - (float) $saldo->qty_base > self::EPS) {
            throw WarehouseRuleException::fields(['qty' => 'Melebihi isi bin ('.$this->angka((float) $saldo->qty_base).').'], 'BR-LED-02');
        }

        return $qty;
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}
