<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Support\ConvertibleStock;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Uom;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-17 dan TC-CNV-18 — tanda *Bisa dipotong* hanya untuk mode Potong
 * (A-285) dan mode Potong hanya bila saklar per potong menyala (A-284, BR-GEN-12).
 */
class ConversionKindTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    #[Test]
    public function tc_cnv_17_ganti_kemasan_dan_rakit_tanpa_tanda_bisa_dipotong(): void
    {
        $this->assertFalse((bool) $this->baut->is_cuttable);

        // Barang biasa tanpa tanda Bisa dipotong kini menjadi calon Ganti kemasan, bukan calon Potong.
        $stok = app(ConvertibleStock::class);
        $kunci = $this->kunciCnv($this->binA, $this->baut);
        $this->assertTrue($stok->selectable($this->gudang, ConversionType::Repack)->has($kunci));
        $this->assertFalse($stok->selectable($this->gudang, ConversionType::Cut)->has($kunci));

        $kemasan = $this->cnv(
            [['key' => $kunci, 'qty_base' => 5]],
            [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 5]],
            ['conversion_type' => 'repack'],
        );
        $this->assertSame(ConversionType::Repack, $kemasan->conversion_type);

        $rakit = $this->cnv(
            [['key' => $kunci, 'qty_base' => 4]],
            [['kind' => 'output', 'item_id' => $this->kabel->id, 'qty_base' => 1]],
            ['conversion_type' => 'assemble'],
        );
        $this->assertSame(ConversionStatus::Draft, $rakit->status);

        // Potong tetap menuntut tanda Bisa dipotong.
        $this->gagalCnv(fn () => $this->cnv([['key' => $kunci, 'qty_base' => 5]], [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 5]]), 'BR-CNV-03');

        // Barang bernomor seri (bukan aset) tidak pernah dikonversi.
        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $meter = $this->buatItem('METERAN-SN', TrackingMode::Serial, $pcs);
        $serial = Serial::create(['item_id' => $meter->id, 'serial_no' => 'MTR-1', 'acquired_at' => now()->toDateString()]);
        $this->stok($this->binA, $meter, 1, ['serial_id' => $serial->id]);
        $this->assertFalse($stok->selectable($this->gudang, ConversionType::Repack)->contains(fn (array $c) => $c['item_id'] === (int) $meter->id));
        $this->gagalCnv(fn () => $this->cnv(
            [['key' => $this->kunciCnv($this->binA, $meter), 'qty_base' => 1]],
            [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 1]],
            ['conversion_type' => 'assemble'],
        ), 'BR-LED-03');
    }

    #[Test]
    public function tc_cnv_18_mode_potong_mengikuti_saklar_per_potong(): void
    {
        // Draf Potong dibuat saat saklar menyala.
        $staf = $this->staf();
        $draf = $this->cnvPotong($staf);

        FeatureSetting::toggle('piece', false);

        $this->assertNotContains(ConversionType::Cut, ConversionType::available(false));
        $this->assertContains(ConversionType::Cut, ConversionType::available(false, ConversionType::Cut));

        // Potong baru ditolak; layar tanpa tombol Potong dan bawaan Ganti kemasan.
        $this->gagalCnv(fn () => $this->cnvPotong(), 'BR-GEN-12');

        Livewire::actingAs($this->staf())->test(ConversionForm::class)
            ->assertSet('form.conversion_type', 'repack')
            ->assertDontSeeHtml('id="cnv-jenis-cut"')
            ->assertSeeHtml('id="cnv-jenis-repack"');

        // Draf Potong lama tetap bisa dibuka dan diselesaikan.
        Livewire::actingAs($staf)->test(ConversionForm::class, ['conversion' => $draf])
            ->assertSeeHtml('id="cnv-jenis-cut"');

        $this->assertSame(ConversionStatus::Completed, $this->selesai($draf, $staf)->status);
    }
}
