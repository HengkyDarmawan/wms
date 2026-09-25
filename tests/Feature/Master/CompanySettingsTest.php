<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\CompanySettingsForm;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Livewire\DiscrepancyList;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-27 — layar Pengaturan company (A-230): izin lihat/ubah, simpan ambang
 * & saklar & zona waktu, validasi rentang, hanya kunci yang berubah ditulis,
 * nilai baru langsung dipakai kode pemakainya, peringatan fitur yang masih
 * dipakai item.
 */
class CompanySettingsTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_27_pengaturan_company_izin_simpan_validasi_dan_efek(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff');
        $admin->forgetPermissionCache();
        $staf->forgetPermissionCache();

        $this->actingAs($admin)->get($this->tenantUrl('settings/company'))->assertOk()->assertSee(__('Pengaturan company'));
        $this->actingAs($staf)->get($this->tenantUrl('settings/company'))->assertForbidden();

        Item::create(['code' => 'LOT-1', 'name' => 'Item berlot', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::Lot, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id')]);

        // Bawaan tampil; item berlot memicu peringatan pada saklar lot.
        $layar = Livewire::actingAs($admin)->test(CompanySettingsForm::class)
            ->assertSet('nilai.receipt_confirm_days', 3)
            ->assertSet('nilai.asset_life_alert_pct', 20.0)
            ->assertSet('fitur.qc', true)
            ->assertSee(__('dipakai :n item', ['n' => 1]));

        // Validasi rentang: 0 hari & 150 % ditolak tanpa menulis apa pun.
        $layar->set('nilai.receipt_confirm_days', 0)
            ->set('nilai.asset_life_alert_pct', 150)
            ->call('simpan')
            ->assertHasErrors(['nilai.receipt_confirm_days', 'nilai.asset_life_alert_pct']);
        $this->assertNull(CompanySetting::query()->find('receipt_confirm_days'));

        // Simpan: hanya kunci yang berubah ditulis; saklar & zona waktu ikut.
        $layar->set('nilai.receipt_confirm_days', 5)
            ->set('nilai.asset_life_alert_pct', 20)
            ->set('nilai.count_tolerance_pct', 2.5)
            ->set('fitur.qc', false)
            ->set('timezone', 'Asia/Makassar')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '')
            ->assertSet('nilai.receipt_confirm_days', 5)
            ->assertSet('timezone', 'Asia/Makassar');

        $this->assertSame(5, CompanySetting::get('receipt_confirm_days'));
        $this->assertSame(2.5, CompanySetting::get('count_tolerance_pct'));
        $this->assertNull(CompanySetting::query()->find('review_sla_days'), 'Kunci yang tidak berubah dari bawaan tidak ditulis.');
        $this->assertFalse(FeatureSetting::enabled('qc'));
        $this->assertTrue(FeatureSetting::enabled('lot'));
        $this->assertSame('Asia/Makassar', tenant()->refresh()->timezone);

        // Efek: kode pemakai membaca nilai baru.
        $this->assertSame(5, app(ConfirmDelivery::class)->ambangKonfirmasi());
        $this->assertSame(7, (int) CompanySetting::get(DiscrepancyList::AMBANG, 7));

        // Zona waktu asing ditolak; staf tanpa izin ubah tidak bisa menyimpan.
        $layar->set('timezone', 'Europe/Paris')->call('simpan')->assertHasErrors('timezone');
        $this->assertSame('Asia/Makassar', tenant()->refresh()->timezone);

        // Tanpa penanganan pengecualian, penolakan otorisasi di dalam panggilan Livewire naik sebagai pengecualian asli.
        $this->withoutExceptionHandling();

        try {
            Livewire::actingAs($this->makeUser('warehouse_head'))->test(CompanySettingsForm::class)->call('simpan');
            $this->fail('Tanpa company_setting.manage seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame(5, CompanySetting::get('receipt_confirm_days'));
        }

        tenant()->forceFill(['timezone' => 'Asia/Jakarta'])->save();
    }
}
