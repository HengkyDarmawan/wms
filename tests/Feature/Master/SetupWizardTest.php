<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\SetupWizard;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-21 — wizard setup awal company: langkah dihitung dari data,
 * persetujuan ketentuan, tanda selesai hanya bila langkah wajib beres (A-191).
 */
class SetupWizardTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_21_wizard_setup_awal(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('/'))->assertOk()->assertSee(__('Lanjutkan setup'));
        $this->actingAs($admin)->get($this->tenantUrl('setup'))->assertOk()->assertSee(__('Buat gudang'));
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('setup'))->assertForbidden();

        // Belum lengkap: tidak bisa ditandai selesai; setujui tanpa centang ditolak.
        $this->actingAs($admin)->post($this->tenantUrl('setup/complete'))->assertSessionHasErrors('setup');
        $this->actingAs($admin)->post($this->tenantUrl('setup/terms'))->assertSessionHasErrors('agree');
        $this->actingAs($admin)->post($this->tenantUrl('setup/terms'), ['agree' => 1])->assertSessionHasNoErrors();
        $this->assertSame(SetupWizard::TERMS_VERSION, CompanySetting::get(SetupWizard::TERMS_KEY)['version']);

        // Lengkapi langkah wajib.
        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG', 'name' => 'Gudang Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        Bin::create(['warehouse_id' => $gudang->id, 'code' => 'CKG-A1', 'bin_type' => BinType::Storage]);
        Item::create(['code' => 'BAUT', 'name' => 'Baut', 'status' => 'active', 'tracking_mode' => 'none',
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id')]);
        $this->makeProject();

        $sisa = app(SetupWizard::class)->progress()['required_left'];
        $this->assertSame(0, $sisa, collect(app(SetupWizard::class)->steps())->where('done', false)->where('optional', false)->pluck('key')->implode(','));

        $this->actingAs($admin)->post($this->tenantUrl('setup/complete'))->assertRedirect($this->tenantUrl('/'));
        $this->assertTrue(app(SetupWizard::class)->isCompleted());
        $this->actingAs($admin)->get($this->tenantUrl('/'))->assertOk()->assertDontSee(__('Lanjutkan setup'));
    }
}
