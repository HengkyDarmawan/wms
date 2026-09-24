<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-14 s.d. TC-GRN-17 — QC sebagai langkah per baris (BR-GRN-02, A-78).
 */
class QcTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    #[Test]
    public function tc_grn_14_lolos_memindah_karantina_ke_penerimaan_tersedia_tanpa_kejadian(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 30]]);
        $kejadianSebelum = StockEvent::query()->count();

        $this->qc($grn, 0, QcResult::Passed, false);

        $this->assertSame(0.0, $this->saldo($this->binSistem($this->gudang, BinType::Quarantine), $this->kabel, StockStatus::Quarantine));
        $this->assertSame(30.0, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->kabel));
        $this->assertSame($kejadianSebelum, StockEvent::query()->count(), 'QC adalah perpindahan dalam gudang, tanpa kejadian.');
        $this->assertSame(QcResult::Passed, $grn->lines()->first()->qc_result);
    }

    #[Test]
    public function tc_grn_15_ditolak_wajib_alasan_dan_menjadi_rusak_di_karantina(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 8]]);

        try {
            $this->qc($grn, 0, QcResult::Rejected, false);
            $this->fail('Penolakan QC tanpa alasan seharusnya ditolak.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $this->qc($grn, 0, QcResult::Rejected);

        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);
        $this->assertSame(8.0, $this->saldo($karantina, $this->kabel, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($karantina, $this->kabel, StockStatus::Quarantine));
        $this->assertNotNull($grn->lines()->first()->qc_reason_id);
    }

    #[Test]
    public function tc_grn_16_karantina_lalu_lolos_setelah_grn_selesai_membuat_put(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 12]]);

        $this->qc($grn, 0, QcResult::Quarantined, false);
        $this->assertSame(12.0, $this->saldo($this->binSistem($this->gudang, BinType::Quarantine), $this->kabel, StockStatus::Quarantine));

        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());
        $this->assertSame(0, $grn->putawayTasks()->count(), 'Baris dikarantina belum boleh di-put-away.');

        $this->qc($grn, 0, QcResult::Passed, false);

        $put = $grn->putawayTasks()->with('lines')->sole();
        $this->assertSame(12.0, (float) $put->lines->first()->qty_base);
        $this->assertSame((int) $this->binSistem($this->gudang, BinType::Receiving)->id, (int) $put->lines->first()->from_bin_id);
    }

    #[Test]
    public function tc_grn_17_baris_tanpa_qc_atau_sudah_final_tidak_bisa_di_qc(): void
    {
        $grn = $this->grnDiterima([
            ['item_id' => $this->baut->id, 'qty_received' => 5],
            ['item_id' => $this->kabel->id, 'qty_received' => 5],
        ]);

        foreach ([[0, QcResult::Passed], [1, QcResult::Rejected], [1, QcResult::Passed]] as $i => [$idx, $hasil]) {
            if ($i === 1) {
                $this->qc($grn, $idx, $hasil);

                continue;
            }

            try {
                $this->qc($grn, $idx, $hasil);
                $this->fail('Baris ini tidak menunggu QC.');
            } catch (ReceiptRuleException $e) {
                $this->assertSame('BR-GRN-02', $e->rule);
            }
        }
    }
}
