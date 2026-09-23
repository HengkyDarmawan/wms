<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vendor;
use Database\Seeders\Tenant\MasterDemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-22 dan TC-MST-23 — izin layar (BR-GEN-09) dan seeder demo
 * (docs/00-akun-uji.md §2).
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

        // Empat item mewakili empat mode pelacakan.
        $this->assertEqualsCanonicalizing(
            ['none', 'lot', 'serial', 'piece'],
            Item::query()->pluck('tracking_mode')->map(fn ($m) => $m->value)->unique()->values()->all(),
        );

        $pipa = Item::query()->where('code', 'PIPA-PVC-4')->firstOrFail();

        $this->assertTrue($pipa->is_cuttable);
        $this->assertSame('M', $pipa->baseUom->code, 'Item per potong memakai satuan panjang (BR-STK-09).');
        $this->assertSame(2, $pipa->vendors()->count());

        // Konversi kemasan "1 batang = 6 m" tersimpan.
        $konversi = $pipa->uomConversions()->first();

        $this->assertNotNull($konversi);
        $this->assertSame(6.0, (float) $konversi->qty_base);
        $this->assertTrue($konversi->is_nominal_piece);

        // Seeder aman dijalankan dua kali.
        (new MasterDemoSeeder)->run();

        $this->assertSame(2, Client::query()->count());
        $this->assertSame(4, Item::query()->count());
    }
}
