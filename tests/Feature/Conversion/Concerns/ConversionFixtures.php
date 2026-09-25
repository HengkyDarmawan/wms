<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Conversion\Actions\CompleteConversion;
use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConvertibleStock;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Waste\Actions\CreateWasteDisposal;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Support\DisposableWaste;
use Tests\Feature\Return\Concerns\ReturnFixtures;

/**
 * Bahan uji modul Konversi & Waste: gudang & proyek TransferFixtures lewat
 * ReturnFixtures (CKG dengan binA/binB, proyek dengan Gudang Site KRW1/KRW2,
 * rantai retur), pipa per potong yang bisa dipotong (minimum offcut 0,5 m,
 * kerf 0,005 m), dan potongan 6 m di binA.
 */
trait ConversionFixtures
{
    use ReturnFixtures;

    protected Piece $batang;

    protected function siapkanKonversi(): void
    {
        $this->siapkanTransfer();

        $this->pipa->forceFill(['is_cuttable' => true, 'min_offcut_length' => 0.5, 'kerf' => 0.005])->save();
        $this->batang = $this->potongan($this->binA, 6.0);
    }

    protected function potongan(Bin $bin, float $panjang, ?Item $item = null): Piece
    {
        $item ??= $this->pipa;

        $piece = Piece::create([
            'item_id' => $item->id,
            'piece_no' => Piece::nextPieceNo(),
            'length' => $panjang,
            'origin_type' => 'grn',
        ]);

        $this->stok($bin, $item, $panjang, ['piece_id' => $piece->id]);

        return $piece;
    }

    /** @param  array<string, int|null>  $turunan */
    protected function kunciCnv(Bin $bin, Item $item, array $turunan = []): string
    {
        return ConvertibleStock::key((int) $bin->id, (int) $item->id, $turunan['lot_id'] ?? null, $turunan['piece_id'] ?? null);
    }

    protected function kunciBatang(?Piece $piece = null): string
    {
        return $this->kunciCnv($this->binA, $this->pipa, ['piece_id' => ($piece ?? $this->batang)->id]);
    }

    protected function staf(): User
    {
        return $this->makeUser('warehouse_staff');
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<int, array<string, mixed>>  $outputs
     * @param  array<string, mixed>  $header
     */
    protected function cnv(array $inputs, array $outputs, array $header = [], ?User $actor = null): Conversion
    {
        return app(CreateConversion::class)->handle($header + [
            'project_id' => $this->proyek->id,
            'warehouse_id' => $this->gudang->id,
            'conversion_type' => 'cut',
            'notes' => 'Konversi uji',
        ], $inputs, $outputs, $actor ?? $this->staf());
    }

    /** Pipa 6 m → 2 × 2,5 m output + offcut 0,99 m + kerf 0,01 m. */
    protected function cnvPotong(?User $actor = null): Conversion
    {
        return $this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [
                ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 2.5, 'count' => 2],
                ['kind' => 'offcut', 'qty_base' => 0.99],
                ['kind' => 'kerf', 'qty_base' => 0.01],
            ],
            [],
            $actor,
        );
    }

    protected function selesai(Conversion $cnv, ?User $actor = null): Conversion
    {
        return app(CompleteConversion::class)->handle($cnv->refresh(), $actor ?? $this->staf());
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, reason_code_id
     * @param  array<string, mixed>  $header
     */
    protected function wst(array $lines, array $header = [], ?User $actor = null): WasteDisposal
    {
        return app(CreateWasteDisposal::class)->handle($header + [
            'warehouse_id' => $this->gudang->id,
            'project_id' => $this->proyek->id,
            'disposition' => 'disposed',
        ], $lines, $actor ?? $this->staf());
    }

    protected function kunciWaste(Bin $bin, Item $item, string $status = 'damaged', array $turunan = []): string
    {
        return DisposableWaste::key((int) $bin->id, (int) $item->id, $turunan['lot_id'] ?? null, $turunan['serial_id'] ?? null, $turunan['piece_id'] ?? null, $status);
    }

    protected function gagalCnv(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ConversionRuleException|WasteRuleException|LedgerException|ApprovalRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}
