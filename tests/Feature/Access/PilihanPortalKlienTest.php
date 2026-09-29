<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Livewire\PortalRequestDetail;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Shared\Livewire\ReportViewer;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-52 — sapuan keamanan cari ke server untuk akun Klien (A-354, A-396):
 * setiap `<x-pilih server>` yang bisa dibuka dengan izin akun Klien (form &
 * daftar REQ, tambahan baris portal, form Retur portal, layar laporan) hanya
 * memberi proyek milik kliennya — juga saat akun itu tanpa penugasan proyek
 * (id cakupan kosong = "semua") — dan tidak pernah memberi label untuk proyek
 * klien lain, pengguna internal, vendor, atau bin.
 */
class PilihanPortalKlienTest extends TenantTestCase
{
    private Project $proyek;

    private Project $lain;

    private Item $baut;

    private User $klien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $this->lain = $this->makeProject();

        app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->baut = Item::create([
            'code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        // Keadaan terburuk: akun Klien tanpa penugasan proyek (accessibleProjectIds = null).
        $this->klien = $this->makeUser('client_user', ScopeType::All, null, ['client_id' => $this->proyek->client_id]);
    }

    /** @return list<int> */
    private function hasil($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    #[Test]
    public function tc_acc_52_akun_klien_hanya_mendapat_proyek_kliennya_di_semua_cari_server(): void
    {
        $kata = 'PRU';

        // Form REQ: proyek hanya milik klien; proyek klien lain tidak diberi label.
        $form = Livewire::actingAs($this->klien)->test(RequestForm::class)->call('cariPilihan', 'form.project_id', $kata);
        $this->assertSame([(int) $this->proyek->id], $this->hasil($form));
        $form->call('labelPilihan', 'form.project_id', (string) $this->lain->id);
        $this->assertNull($form->effects['returns'][0] ?? null);

        // Daftar REQ: saringan proyek sama.
        $daftar = Livewire::actingAs($this->klien)->test(RequestList::class)->call('cariPilihan', 'projectFilter', $kata);
        $this->assertSame([(int) $this->proyek->id], $this->hasil($daftar));

        // Form Retur (portal): proyek hanya milik klien; id klien lain ditolak di isian.
        $retur = Livewire::actingAs($this->klien)->test(ReturnForm::class)
            ->assertSet('form.project_id', (string) $this->proyek->id)
            ->call('cariPilihan', 'form.project_id', $kata);
        $this->assertSame([(int) $this->proyek->id], $this->hasil($retur));
        $retur->call('labelPilihan', 'form.project_id', (string) $this->lain->id);
        $this->assertNull($retur->effects['returns'][0] ?? null);
        $retur->set('form.project_id', (string) $this->lain->id)->call('simpan')->assertHasErrors('form.project_id');

        // Tambahan baris portal: item (master bersama) saja; model lain tidak bisa dicari.
        $req = app(SubmitRequest::class)->handle(app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
        ], [['item_id' => $this->baut->id, 'qty_base' => 2]], $this->klien), $this->klien);
        $portal = Livewire::actingAs($this->klien)->test(PortalRequestDetail::class, ['request' => $req])
            ->call('mintaTambah')->call('cariPilihan', 'barisBaru.0.item_id', 'baut');
        $this->assertSame([(int) $this->baut->id], $this->hasil($portal));
        foreach (['form.project_id', 'projectFilter', 'reasonCode'] as $model) {
            $portal->call('cariPilihan', $model, $kata);
            $this->assertSame([], $this->hasil($portal), $model);
        }

        // Layar laporan (internal saja): Klien tanpa pilihan walau ber-`request.view`.
        $laporan = Livewire::actingAs($this->klien)->test(ReportViewer::class, ['reportKey' => 'daftar-req'])
            ->call('cariPilihan', 'filters.project_id', $kata);
        $this->assertSame([], $this->hasil($laporan));
    }
}
