<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\SaveUom;
use App\Domain\Master\Actions\SaveUomCategory;
use App\Domain\Master\Actions\SaveVendor;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-07 s.d. TC-MST-10 — vendor (A-52, A-53) dan satuan
 * (D-11, BR-MST-03, Blueprint §6.5).
 */
class VendorUomTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_07_vendor_toko_online_tersimpan_dengan_jenisnya(): void
    {
        $vendor = app(SaveVendor::class)->handle(null, [
            'code' => 'tokopedia-alat',
            'name' => 'Tokopedia Toko Alat',
            'vendor_type' => VendorType::OnlineMarketplace->value,
            'phone' => '+628123456789',
        ]);

        $this->assertSame('TOKOPEDIA-ALAT', $vendor->code);
        $this->assertSame(VendorType::OnlineMarketplace, $vendor->vendor_type);
        $this->assertSame(VendorStatus::Active, $vendor->status);
        $this->assertTrue($vendor->is_active);
    }

    #[Test]
    public function tc_mst_07b_vendor_aktif_butuh_kontak(): void
    {
        try {
            app(SaveVendor::class)->handle(null, [
                'code' => 'TANPA-KONTAK',
                'name' => 'Vendor Tanpa Kontak',
            ]);
            $this->fail('Vendor aktif tanpa telepon dan email seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('A-53', $e->rule);
            $this->assertArrayHasKey('phone', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_mst_08_vendor_sementara_dibuat_cepat_lalu_dilengkapi(): void
    {
        // A-53: dibuat saat memesan, tanpa kode dan tanpa kontak.
        $vendor = app(SaveVendor::class)->provisional('Toko Besi Sudirman', VendorType::Shop);

        $this->assertSame(VendorStatus::Provisional, $vendor->status);
        $this->assertTrue($vendor->isProvisional());
        $this->assertNotSame('', $vendor->code);

        // Admin melengkapi datanya, statusnya menjadi aktif.
        $vendor = app(SaveVendor::class)->handle($vendor, [
            'name' => 'Toko Besi Sudirman',
            'vendor_type' => VendorType::Shop->value,
            'status' => VendorStatus::Active->value,
            'phone' => '+62215550000',
            'contact_name' => 'Pak Rudi',
        ]);

        $this->assertSame(VendorStatus::Active, $vendor->status);
        $this->assertFalse($vendor->isProvisional());
    }

    #[Test]
    public function tc_mst_09_kategori_satuan_wajib_punya_satuan_acuan(): void
    {
        try {
            app(SaveUomCategory::class)->handle(null, ['code' => 'TEKANAN', 'name' => 'Tekanan']);
            $this->fail('Kategori satuan tanpa acuan seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-03', $e->rule);
            $this->assertArrayHasKey('reference_uom_id', $e->fieldErrors);
        }

        $kategori = app(SaveUomCategory::class)->handle(
            null,
            ['code' => 'TEKANAN', 'name' => 'Tekanan'],
            ['code' => 'BAR', 'name' => 'Bar'],
        );

        $acuan = $kategori->referenceUom;

        $this->assertNotNull($acuan);
        $this->assertSame('BAR', $acuan->code);
        $this->assertSame(1.0, (float) $acuan->factor_to_reference);
        $this->assertTrue($acuan->isReference());
    }

    #[Test]
    public function tc_mst_09b_satuan_acuan_tidak_bisa_dinonaktifkan(): void
    {
        $meter = Uom::query()->where('code', 'M')->firstOrFail();

        try {
            app(SaveUom::class)->deactivate($meter);
            $this->fail('Satuan acuan seharusnya tidak bisa dinonaktifkan.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-03', $e->rule);
        }

        $this->assertTrue($meter->refresh()->is_active);
    }

    #[Test]
    public function tc_mst_10_satuan_baru_di_kategori_panjang_bisa_dipakai_konversi(): void
    {
        $panjang = UomCategory::query()->where('code', 'LENGTH')->firstOrFail();

        $desimeter = app(SaveUom::class)->handle(null, [
            'code' => 'dm',
            'name' => 'Desimeter',
            'uom_category_id' => $panjang->id,
            'factor_to_reference' => '0,1',
        ]);

        $this->assertSame('DM', $desimeter->code);
        $this->assertSame(0.1, (float) $desimeter->factor_to_reference);

        // 25 dm = 2,5 m.
        $meter = Uom::query()->where('code', 'M')->firstOrFail();

        $this->assertEqualsWithDelta(2.5, $desimeter->convertTo($meter, 25), 0.0001);

        // Konversi lintas kategori ditolak (D-11).
        $kg = Uom::query()->where('code', 'KG')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $desimeter->convertTo($kg, 1);
    }

    #[Test]
    public function tc_mst_10b_kategori_satuan_tidak_bisa_dipindah(): void
    {
        $panjang = UomCategory::query()->where('code', 'LENGTH')->firstOrFail();
        $berat = UomCategory::query()->where('code', 'WEIGHT')->firstOrFail();

        $satuan = app(SaveUom::class)->handle(null, [
            'code' => 'HM',
            'name' => 'Hektometer',
            'uom_category_id' => $panjang->id,
            'factor_to_reference' => 100,
        ]);

        try {
            app(SaveUom::class)->handle($satuan, [
                'name' => 'Hektometer',
                'uom_category_id' => $berat->id,
                'factor_to_reference' => 100,
            ]);
            $this->fail('Pindah kategori satuan seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }
}
