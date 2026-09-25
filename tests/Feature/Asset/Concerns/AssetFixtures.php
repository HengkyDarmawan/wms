<?php

declare(strict_types=1);

namespace Tests\Feature\Asset\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Asset\Actions\InspectAsset;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Models\AssetInspection;
use App\Domain\Master\Enums\MeterUnit;
use App\Domain\Master\Models\Serial;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Return\Concerns\ReturnFixtures;

/**
 * Bahan uji modul Aset: gudang & proyek TransferFixtures lewat ReturnFixtures
 * (CKG, proyek dengan Gudang Site KRW1 + bin On-site), genset (aset, meter jam)
 * GNS-01 di bin CKG, dan rantai pinjam → kembali memakai SJ/RET sungguhan.
 */
trait AssetFixtures
{
    use ReturnFixtures;

    protected Serial $gns;

    protected Bin $onSite;

    protected function siapkanAset(): void
    {
        $this->siapkanTransfer();
        Storage::fake('local');

        $this->onSite = app(EnsureSystemBins::class)->onSiteBin($this->krw1, $this->proyek);
        $this->proyek->forceFill(['target_end_date' => now()->addDays(30)->toDateString()])->save();

        $this->gns = Serial::create([
            'item_id' => $this->genset->id,
            'serial_no' => 'GNS-01',
            'meter_unit' => MeterUnit::Hour,
            'meter_total' => 100,
            'acquired_at' => now()->subDays(100)->toDateString(),
            'expected_life_days' => 1000,
            'expected_life_hours' => 1000,
            'condition_grade' => 'A',
        ]);
        $this->stok($this->binB, $this->genset, 1, ['serial_id' => $this->gns->id]);
    }

    /** Aset dipinjamkan ke proyek: REQ pinjam → PCK → SJ → bukti terima. */
    protected function pinjamkan(): Shipment
    {
        return $this->terimaSj($this->terkirimKeKlien($this->genset, 1, ['line_ownership' => 'loan']));
    }

    /** Aset kembali: RET dari bin On-site → GRN retur ke bin Retur CKG. */
    protected function kembalikan(?Serial $serial = null): GoodsReturn
    {
        $serial ??= $this->gns;
        $ret = $this->ret([['key' => implode(':', ['asset', $this->onSite->id, $serial->item_id, $serial->id]), 'qty_base' => 1]]);
        $this->grnRetur($ret);

        return $ret->refresh();
    }

    protected function ast(?Serial $serial = null): AssetHandover
    {
        return AssetHandover::query()->withoutGlobalScopes()->where('serial_id', ($serial ?? $this->gns)->id)->latest('id')->firstOrFail();
    }

    /** @param  array<string, mixed>  $data */
    protected function periksa(AssetHandover $ast, array $data = [], ?User $actor = null, bool $foto = true): AssetInspection
    {
        return app(InspectAsset::class)->handle($ast->refresh(), $data + [
            'condition_grade' => 'A',
            'condition_score' => 90,
            'component_notes' => "Mesin: normal\nPanel: bersih",
            'meter_in' => 150,
        ], $foto ? UploadedFile::fake()->image('periksa.jpg') : null, $actor ?? $this->makeUser('warehouse_staff'));
    }

    protected function binRetur(): Bin
    {
        return $this->binSistem($this->gudang, BinType::Return);
    }

    protected function gagalAset(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (AssetRuleException|ReturnRuleException|AdjustmentRuleException|LedgerException|ApprovalRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}
