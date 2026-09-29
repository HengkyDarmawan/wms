<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Adjustment\Livewire\AdjustmentForm;
use App\Domain\Count\Livewire\CountEntry;
use App\Domain\Count\Livewire\StockCountDetail;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Uom;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-ADJ-14, TC-OPN-23 — `<x-pilih>` di menu Opname & penyesuaian (A-394):
 * bin & item baris ADJ manual dan item temuan hitung dicari ke server dengan
 * daftar & cakupan yang sama seperti dulu; id di luar daftar ditolak di
 * isiannya; penghitung sesi dimuat sekaligus.
 */
class PilihanOpnameTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    /** @return list<int> */
    private function nilai($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    #[Test]
    public function tc_adj_14_bin_dan_item_adj_dicari_ke_server_sesuai_cakupan(): void
    {
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Gudang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $binBks = Bin::create(['warehouse_id' => $bks->id, 'code' => 'BKS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $beku = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B09', 'bin_type' => BinType::Storage, 'bin_status' => 'frozen']);
        $mati = $this->itemUji('BAUT-OPN-MATI', TrackingMode::None, Uom::query()->where('code', 'PCS')->value('id'), ['status' => ItemStatus::Inactive]);

        $form = Livewire::actingAs($this->staf1)->test(AdjustmentForm::class)->assertSeeHtml('id="adj-gudang"')
            ->call('cariPilihan', 'rows.0.bin_id', 'CKG');
        $this->assertSame([], $this->nilai($form), 'Tanpa gudang terpilih, bin tidak bisa dicari.');

        $form->set('form.warehouse_id', (string) $this->gudang->id)->call('cariPilihan', 'rows.0.bin_id', 'R01');
        $this->assertContains((int) $this->binA->id, $this->nilai($form));
        $this->assertNotContains((int) $beku->id, $this->nilai($form), 'Bin tidak aktif tidak ditawarkan.');

        // Gudang di luar cakupan dikirim dari browser: bin-nya tetap tidak bisa dicari.
        $form->set('form.warehouse_id', (string) $bks->id)->call('cariPilihan', 'rows.0.bin_id', 'BKS');
        $this->assertSame([], $this->nilai($form));

        // Item: semua status (daftar lama).
        $form->call('cariPilihan', 'rows.0.item_id', 'BAUT-OPN');
        $this->assertEqualsCanonicalizing([(int) $this->baut->id, (int) $mati->id], $this->nilai($form));

        $form->set('form.warehouse_id', (string) $this->gudang->id)->set('form.reason', 'x')
            ->set('rows.0.bin_id', (string) $binBks->id)->set('rows.0.item_id', '999999')->set('rows.0.qty', '1')
            ->call('simpan')->assertHasErrors(['rows.0.bin_id', 'rows.0.item_id']);

        // Tanpa `adjustment.create`: pencarian kosong.
        $this->actingAs($this->auditor);
        $form->call('cariPilihan', 'rows.0.item_id', 'BAUT');
        $this->assertSame([], $this->nilai($form));
    }

    #[Test]
    public function tc_opn_23_item_temuan_dicari_ke_server_dan_penghitung_dimuat(): void
    {
        $sesi = $this->sesiBerjalan(['bin_ids' => [$this->binA->id], 'team_user_ids' => [$this->staf1->id]]);
        $tugas = CountAssignment::query()->where('stock_count_id', $sesi->id)->sole();

        Livewire::actingAs($this->kepala)->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->assertSeeHtml('id="penghitung-'.$tugas->id.'"');

        $hitung = Livewire::actingAs($this->staf1)->test(CountEntry::class, ['countAssignment' => $tugas])
            ->call('cariPilihan', 'temuan.item_id', 'OPN');
        $this->assertSame([], $this->nilai($hitung), 'Tanpa form temuan terbuka, item tidak bisa dicari.');

        $hitung->set('tambahTemuan', true)->call('cariPilihan', 'temuan.item_id', 'OPN');
        $this->assertEqualsCanonicalizing([(int) $this->baut->id, (int) $this->semen->id], $this->nilai($hitung), 'Hanya item tanpa pelacakan / ber-lot.');

        $hitung->set('temuan.item_id', (string) $this->genset->id)->set('temuan.qty', '1')
            ->call('catatTemuan')->assertHasErrors('temuan.item_id');

        // Penghitung lain (bukan pemilik tugas) tidak mendapat hasil.
        $this->actingAs($this->staf2);
        $hitung->call('cariPilihan', 'temuan.item_id', 'OPN');
        $this->assertSame([], $this->nilai($hitung));
    }
}
