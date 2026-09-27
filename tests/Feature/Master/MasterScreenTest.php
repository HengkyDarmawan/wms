<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vendor;
use Database\Seeders\Tenant\MasterDemoSeeder;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-22 dan TC-MST-23 — izin layar (BR-GEN-09) dan seeder demo
 * (docs/00-akun-uji.md §2); TC-MST-29 — form item: bagian opsional dalam tab (A-282).
 */
class MasterScreenTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_22_tanpa_izin_item_layar_ditolak(): void
    {
        // Driver hanya punya item.view, tidak boleh membuka form item.
        $driver = $this->makeUser('driver');

        $this->actingAs($driver)
            ->get($this->tenantUrl('items'))
            ->assertOk();

        $this->actingAs($driver)
            ->get($this->tenantUrl('items/create'))
            ->assertForbidden();

        // Klien tidak pernah sampai ke layar master: dialihkan ke portal (BR-PRJ-07).
        $proyek = $this->makeProject();
        $klien = $this->makeUser('client_user', ScopeType::Project, $proyek->id, [
            'client_id' => $proyek->client_id,
        ]);

        $this->actingAs($klien)
            ->get($this->tenantUrl('items'))
            ->assertRedirect(route('portal.dashboard'));

        $this->assertFalse($klien->hasPermission('item.view'));
    }

    #[Test]
    public function tc_mst_22b_kepala_gudang_boleh_mengubah_item_tapi_bukan_klien(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);

        $this->assertTrue($kepala->hasPermission('item.update'));
        $this->assertFalse($kepala->hasPermission('item.create'));
        $this->assertFalse($kepala->hasPermission('client.create'));
        $this->assertTrue($kepala->hasPermission('reference.manage'));

        $this->actingAs($kepala)->get($this->tenantUrl('clients'))->assertOk();
        $this->actingAs($kepala)->get($this->tenantUrl('items/create'))->assertForbidden();
    }

    #[Test]
    public function tc_mst_22c_admin_company_membuka_seluruh_layar_master(): void
    {
        $admin = $this->makeUser('company_admin');

        foreach ([
            'clients',
            'projects',
            'vendors',
            'items',
            'items/create',
            'item-categories',
            'uoms',
            'references',
        ] as $path) {
            $this->actingAs($admin)
                ->get($this->tenantUrl($path))
                ->assertOk(); // @phpstan-ignore-line
        }
    }

    #[Test]
    public function tc_mst_23_seeder_demo_membuat_master_sesuai_akun_uji(): void
    {
        (new MasterDemoSeeder)->run();

        // §2: dua klien, tiga proyek, tiga vendor.
        $this->assertSame(2, Client::query()->count());
        $this->assertSame(3, Project::query()->count());
        $this->assertSame(3, Vendor::query()->count());

        $internal = Project::query()->where('code', 'PRJ-INT')->firstOrFail();

        $this->assertTrue($internal->is_internal);
        $this->assertNull($internal->client_id, 'Proyek Internal tidak punya klien (BR-MST-04).');

        $karawang = Project::query()->where('code', 'PRJ-001')->firstOrFail();

        $this->assertSame('KL1', $karawang->client->code);

        // A-283: empat item mewakili tiga jenis barang; tidak ada item per potong.
        $this->assertEqualsCanonicalizing(
            ['none', 'lot', 'serial'],
            Item::query()->pluck('tracking_mode')->map(fn ($m) => $m->value)->unique()->values()->all(),
        );
        $this->assertSame(
            ['BAUT-M12' => 'standard', 'GENSET-5KVA' => 'serial_tool', 'PIPA-PVC-4' => 'standard', 'SEMEN-PCC-50' => 'expiring'],
            Item::query()->orderBy('code')->get()->mapWithKeys(fn (Item $i) => [$i->code => ItemKind::fromItem($i)?->value])->all(),
        );

        $pipa = Item::query()->where('code', 'PIPA-PVC-4')->firstOrFail();

        $this->assertFalse($pipa->is_cuttable, 'Memotong pipa termasuk pemakaian, bukan jenis barang.');
        $this->assertSame('M', $pipa->baseUom->code, 'Pipa disimpan dalam meter.');
        $this->assertSame(0, $pipa->vendors()->count(), 'A-305: vendor tetap item tidak diisi lagi; saran vendor dari riwayat.');

        // Konversi kemasan "1 batang = 6 m" tersimpan.
        $konversi = $pipa->uomConversions()->first();

        $this->assertNotNull($konversi);
        $this->assertSame(6.0, (float) $konversi->qty_base);
        $this->assertFalse($konversi->is_nominal_piece);

        // Seeder aman dijalankan dua kali.
        (new MasterDemoSeeder)->run();

        $this->assertSame(2, Client::query()->count());
        $this->assertSame(4, Item::query()->count());
    }

    #[Test]
    public function tc_mst_29_form_item_bagian_opsional_dalam_tab(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        // A-283: tab tinggal Stok minimum & Kemasan; Vendor tetap tidak lagi ada.
        $c = Livewire::test(ItemForm::class)
            ->assertSee(__('Pengaturan tambahan'))->assertSee(__('Stok minimum'))
            ->assertSee(__('Titik pesan ulang'))->assertDontSee(__('Vendor tetap'))
            ->set('tabTambahan', 'konversi')->assertSee(__('Belum ada kemasan.'))->assertDontSee(__('Titik pesan ulang'))
            ->set('tabTambahan', 'vendor')->assertSet('tabTambahan', 'stok')
            ->set('tabTambahan', 'ngawur')->assertSet('tabTambahan', 'stok');

        // Galat di tab tertutup: tab itu dibuka otomatis dan pesannya Bahasa Indonesia.
        $c->set('tabTambahan', 'konversi')->set('form.reorder_point', '-5')->call('simpan')
            ->assertHasErrors(['form.reorder_point' => 'min'])
            ->assertSee(__('Titik pesan ulang'))->assertSee('Minimal 0.')->assertSee('Wajib diisi.')
            ->assertDontSee('validation.');

        // Beli/Pinjam mengikuti jenis barang, tanpa pilihan sifat baris.
        $c->set('form.item_kind', 'serial_tool')->assertSee(__('Pinjam — kembali ke gudang'))
            ->set('form.item_kind', 'standard')->assertSee(__('Beli — tidak kembali'))
            ->assertDontSee(__('Sifat baris bawaan'));
    }
}
