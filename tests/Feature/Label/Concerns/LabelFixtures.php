<?php

declare(strict_types=1);

namespace Tests\Feature\Label\Concerns;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Models\GoodsReceipt;
use Illuminate\Support\Collection;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;

/**
 * Bahan uji label kemasan (A-296–A-303): GRN vendor diterima → Selesaikan
 * dengan rencana label → put-away ke bin A.
 */
trait LabelFixtures
{
    use OutboundChain;
    use ReceiptFixtures;

    protected function siapkanLabel(): void
    {
        $this->siapkanPenerimaan();
        // Keputusan pemilik 28 Sep 2026: QC tidak dipakai (bawaan company baru).
        FeatureSetting::seed(['qc' => false], overwrite: true);
    }

    /**
     * GRN vendor $qty diterima lalu diselesaikan dengan $dus × $isi label.
     *
     * @param  array<string, mixed>  $extra  isian baris GRN tambahan
     */
    protected function grnBerlabel(Item $item, float $qty, int $dus, float $isi, array $extra = []): GoodsReceipt
    {
        $grn = $this->grnDiterima([array_merge(['item_id' => $item->id, 'qty_received' => $qty], $extra)]);

        return app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'), [
            $grn->lines()->sole()->id => ['packages' => $dus, 'per_package' => $isi],
        ]);
    }

    /** Put-away semua tugas GRN ke bin saran (bin A). */
    protected function putAway(GoodsReceipt $grn): void
    {
        foreach ($grn->putawayTasks()->withoutGlobalScopes()->get() as $put) {
            app(CompletePutaway::class)->handle($put, [], $this->makeUser());
        }
    }

    /** @return Collection<int, PackageLabel> */
    protected function labelGrn(GoodsReceipt $grn): Collection
    {
        return PackageLabel::query()->where('goods_receipt_id', $grn->id)->whereNull('parent_id')->orderBy('sequence')->get();
    }
}
