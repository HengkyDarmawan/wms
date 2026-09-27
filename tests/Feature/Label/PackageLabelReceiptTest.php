<?php

declare(strict_types=1);

namespace Tests\Feature\Label;

use App\Domain\Label\Actions\CancelLabel;
use App\Domain\Label\Actions\CreateContentLabels;
use App\Domain\Label\Actions\CreatePackageLabels;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Livewire\ReceiptLabels;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Livewire\ReceiptDetail;
use App\Domain\Stock\Models\DocumentSequence;
use App\Domain\Warehouse\Enums\BinType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Label\Concerns\LabelFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-28 s.d. TC-GRN-32, TC-MST-38, TC-TPL-32 — label kemasan induk/isi
 * dibuat saat GRN vendor selesai (A-296), lot otomatis (A-297), put-away
 * memindah bin label, dan pembatalan label (A-301).
 */
class PackageLabelReceiptTest extends TenantTestCase
{
    use LabelFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLabel();
    }

    #[Test]
    public function tc_grn_28_selesai_membuat_label_induk_berurutan_per_item_dan_label_isi_bila_diminta(): void
    {
        $grn = $this->grnBerlabel($this->baut, 30, 3, 12);
        $label = $this->labelGrn($grn);

        $this->assertSame(['BAUT-M12-0001', 'BAUT-M12-0002', 'BAUT-M12-0003'], $label->pluck('code')->all());
        $this->assertSame([12.0, 12.0, 6.0], $label->map(fn ($l) => (float) $l->qty)->all(), 'Kemasan terakhir berisi sisanya.');
        $this->assertTrue($label->every(fn ($l) => $l->status === PackageLabelStatus::InStock && (int) $l->warehouse_id === (int) $this->gudang->id));
        $this->assertSame((int) $this->binSistem($this->gudang, BinType::Receiving)->id, (int) $label->first()->bin_id);
        $this->assertSame('Diterima', $label->first()->moves()->sole()->eventLabel());

        // Nomor urut per item berlanjut di GRN berikutnya.
        $kedua = $this->labelGrn($this->grnBerlabel($this->baut, 5, 1, 5));
        $this->assertSame(['BAUT-M12-0004'], $kedua->pluck('code')->all());

        // Label isi: isi induk dibagi rata; induk tetap Di gudang sebagai wadah.
        $isi = app(CreateContentLabels::class)->handle($label->first(), 12, $this->makeUser());
        $this->assertSame('BAUT-M12-0001-0001', $isi->first()->code);
        $this->assertSame('BAUT-M12-0001-0012', $isi->last()->code);
        $this->assertSame(12.0, (float) $isi->sum('qty'));
        $induk = $label->first()->refresh();
        $this->assertSame(0.0, (float) $induk->qty_remaining);
        $this->assertSame(PackageLabelStatus::InStock, $induk->status);

        // Label isi tidak bisa dibuat dua kali dari induk yang sudah dibagi.
        $this->expectException(LabelRuleException::class);
        app(CreateContentLabels::class)->handle($induk, 3, $this->makeUser());
    }

    #[Test]
    public function tc_grn_29_hanya_bagian_baik_yang_dilabeli_dan_rencana_harus_cocok(): void
    {
        $grn = $this->grnDiterima([[
            'item_id' => $this->baut->id, 'qty_received' => 20, 'qty_damaged' => 4, 'damage_reason_id' => $this->alasan(ReasonContext::Damage),
        ]]);
        $baris = $grn->lines()->sole();

        $this->assertSame(20.0, app(CreatePackageLabels::class)->belumBerlabel($baris), 'Bagian rusak tidak dilabeli.');

        try {
            // 2 × 12 untuk 20 sah (dus terakhir berisi 8, A-296); 3 × 12 berarti dus ketiga kosong.
            app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'), [$baris->id => ['packages' => 3, 'per_package' => 12]]);
            $this->fail('3 × 12 untuk 20 barang Baik: dus ketiga tidak berisi.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame('BR-LBL-02', $e->rule);
        }

        $this->assertSame('received', $grn->refresh()->status->value, 'Penolakan label membatalkan penyelesaian GRN.');

        // Tanpa rencana (pemanggil lama) = tanpa label; label bisa dibuat belakangan.
        $grn = app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser('warehouse_head'));
        $this->assertCount(0, $this->labelGrn($grn));

        app(CreatePackageLabels::class)->handle($grn, [$baris->id => ['packages' => 2, 'per_package' => 10]], $this->makeUser());
        $this->assertSame(0.0, app(CreatePackageLabels::class)->belumBerlabel($baris));
        $this->assertFalse(app(CreatePackageLabels::class)->labelable($baris));
    }

    #[Test]
    public function tc_grn_30_nomor_urut_lebih_dari_9999_menjadi_lima_digit(): void
    {
        DocumentSequence::query()->insert([
            'document_type' => 'LBL', 'segment' => (string) $this->baut->id, 'period' => 'ALL',
            'last_number' => 9999, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $label = $this->labelGrn($this->grnBerlabel($this->baut, 2, 2, 1));

        $this->assertSame(['BAUT-M12-10000', 'BAUT-M12-10001'], $label->pluck('code')->all());
    }

    #[Test]
    public function tc_grn_31_lot_otomatis_dari_grn_dan_batch_vendor_opsional(): void
    {
        $grn = $this->grnBerlabel($this->semen, 10, 2, 5, ['lot_no' => 'batch-77', 'expiry_date' => now()->addYear()->toDateString()]);
        $baris = $grn->lines()->sole();

        $this->assertSame($grn->number.'-01', $baris->lot_no, 'Nomor lot = nomor GRN + urutan baris.');
        $this->assertSame('BATCH-77', $baris->vendor_batch_no);
        $this->assertSame('BATCH-77', $baris->lot->attributes['vendor_batch']);

        $label = $this->labelGrn($grn);
        $this->assertTrue($label->every(fn ($l) => (int) $l->lot_id === (int) $baris->lot_id));

        // Pemindai: kode label menunjuk item & lot asalnya.
        $this->assertSame((int) $label->first()->id, (int) ScanCode::label(strtolower($label->first()->code))->id);
        $this->assertContains(['item_id' => (int) $this->semen->id, 'lot_id' => (int) $baris->lot_id, 'serial_id' => null, 'piece_id' => null],
            ScanCode::resolve($label->first()->code));

        // Batch vendor tetap opsional; kedaluwarsa tetap wajib.
        $tanpaBatch = $this->grnBerlabel($this->semen, 3, 1, 3, ['expiry_date' => now()->addYear()->toDateString()]);
        $this->assertSame($tanpaBatch->number.'-01', $tanpaBatch->lines()->sole()->lot_no);
    }

    #[Test]
    public function tc_grn_32_put_away_memindah_bin_label(): void
    {
        $grn = $this->grnBerlabel($this->baut, 24, 2, 12);
        $this->putAway($grn);

        $label = $this->labelGrn($grn);
        $this->assertSame([(int) $this->binA->id], $label->pluck('bin_id')->map(fn ($b) => (int) $b)->unique()->values()->all());
        $this->assertSame('Ditaruh di bin', $label->first()->lastMove()->eventLabel());
    }

    #[Test]
    public function tc_grn_28b_layar_selesaikan_menanyakan_jumlah_dus_dan_daftar_label_tampil(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 25]]);
        $baris = $grn->lines()->sole();

        $komponen = Livewire::actingAs($this->makeUser('warehouse_head'))->test(ReceiptDetail::class, ['goodsReceipt' => $grn])
            ->call('mintaSelesai')
            ->assertSet('dialog', 'selesai')
            ->assertSet('labelRencana.'.$baris->id.'.packages', '1')
            ->assertSet('labelRencana.'.$baris->id.'.per_package', '25')
            ->set('labelRencana.'.$baris->id.'.packages', '3')
            ->set('labelRencana.'.$baris->id.'.per_package', '10')
            ->call('selesaikan')
            ->assertSet('dialog', '')
            ->assertSee('BAUT-M12-0003');

        $this->assertSame('completed', $grn->refresh()->status->value);
        $this->assertSame([10.0, 10.0, 5.0], $this->labelGrn($grn)->map(fn ($l) => (float) $l->qty)->all());

        // Label isi dari layar GRN, lalu tautan cetaknya.
        $induk = $this->labelGrn($grn)->first();
        Livewire::actingAs($this->makeUser('warehouse_head'))->test(ReceiptLabels::class, ['receiptId' => $grn->id])
            ->call('mintaIsi', $induk->id)
            ->assertSet('dialog', 'isi')
            ->set('nIsi', '5')
            ->call('buatIsi')
            ->assertSee('BAUT-M12-0001')
            ->assertSee('type=label_package', false);

        $this->assertSame(5, PackageLabel::query()->where('parent_id', $induk->id)->count());
        unset($komponen);
    }

    #[Test]
    public function tc_tpl_32_batal_label_beralasan_ikut_membatalkan_label_isi(): void
    {
        $grn = $this->grnBerlabel($this->baut, 12, 1, 12);
        $induk = $this->labelGrn($grn)->first();
        app(CreateContentLabels::class)->handle($induk, 3, $this->makeUser());

        try {
            app(CancelLabel::class)->handle($induk, null, null, $this->makeUser('warehouse_head'));
            $this->fail('Alasan wajib (BR-GEN-11).');
        } catch (LabelRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        app(CancelLabel::class)->handle($induk->refresh(), $this->alasan(ReasonContext::Cancel), 'Label sobek', $this->makeUser('warehouse_head'));

        $this->assertSame(4, PackageLabel::query()->where('status', PackageLabelStatus::Cancelled->value)->count());
        $this->assertSame('Dibatalkan', $induk->lastMove()->eventLabel());

        // Staf gudang tidak boleh membatalkan (adjustment.approve).
        $isi = $this->grnBerlabel($this->baut, 5, 1, 5);
        Livewire::actingAs($this->makeUser('warehouse_staff'))->test(ReceiptLabels::class, ['receiptId' => $isi->id])
            ->call('mintaBatal', $this->labelGrn($isi)->first()->id)
            ->assertForbidden();
    }
}
