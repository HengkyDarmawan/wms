<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Support\MasterCode;
use App\Domain\Master\Support\TrackingCombination;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Aturan murni yang tidak menyentuh database.
 *
 * Sebelumnya matriks kombinasi pelacakan hanya teruji lewat uji fitur yang
 * membuat item sungguhan; di sini setiap kombinasi diperiksa langsung.
 */
class MasterRuleTest extends TestCase
{
    private TrackingCombination $matriks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matriks = new TrackingCombination;
    }

    #[Test]
    public function matriks_kombinasi_meluluskan_yang_sah(): void
    {
        // Tanpa pelacakan, FIFO, habis pakai.
        $this->assertTrue($this->matriks->isValid(
            TrackingMode::None, OwnershipModel::Consumable, RemovalStrategy::Fifo, false,
        ));

        // Lot dengan FEFO dan kedaluwarsa.
        $this->assertTrue($this->matriks->isValid(
            TrackingMode::Lot, OwnershipModel::Consumable, RemovalStrategy::Fefo, true,
        ));

        // Aset bernomor seri.
        $this->assertTrue($this->matriks->isValid(
            TrackingMode::Serial, OwnershipModel::Asset, RemovalStrategy::Manual, false,
        ));

        // Per potong dengan satuan dasar panjang.
        $this->assertTrue($this->matriks->isValid(
            TrackingMode::Piece, OwnershipModel::Consumable, RemovalStrategy::OffcutFirst,
            false, true, 0.5, true,
        ));
    }

    #[Test]
    public function br_stk_08_aset_wajib_serial(): void
    {
        foreach ([TrackingMode::None, TrackingMode::Lot, TrackingMode::Piece] as $mode) {
            $pelanggaran = $this->matriks->violations($mode, OwnershipModel::Asset, null, false);

            $this->assertArrayHasKey('ownership_model', $pelanggaran, $mode->value.' tidak boleh menjadi aset.');
        }

        $this->assertArrayNotHasKey(
            'ownership_model',
            $this->matriks->violations(TrackingMode::Serial, OwnershipModel::Asset, null, false),
        );
    }

    #[Test]
    public function br_stk_11_strategi_di_luar_matriks_ditolak(): void
    {
        // FEFO hanya untuk lot.
        $this->assertArrayHasKey(
            'removal_strategy',
            $this->matriks->violations(TrackingMode::None, OwnershipModel::Consumable, RemovalStrategy::Fefo, true),
        );

        // Sisa potongan dulu hanya untuk per potong.
        $this->assertArrayHasKey(
            'removal_strategy',
            $this->matriks->violations(TrackingMode::Lot, OwnershipModel::Consumable, RemovalStrategy::OffcutFirst, false),
        );
    }

    #[Test]
    public function br_stk_12_kedaluwarsa_hanya_lot_dan_serial(): void
    {
        foreach ([TrackingMode::None, TrackingMode::Piece] as $mode) {
            $this->assertArrayHasKey(
                'has_expiry',
                $this->matriks->violations($mode, OwnershipModel::Consumable, null, true),
            );
        }

        // FEFO menuntut kedaluwarsa menyala.
        $this->assertArrayHasKey(
            'has_expiry',
            $this->matriks->violations(TrackingMode::Lot, OwnershipModel::Consumable, RemovalStrategy::Fefo, false),
        );
    }

    #[Test]
    public function br_stk_09_per_potong_menuntut_satuan_panjang(): void
    {
        $pelanggaran = $this->matriks->violations(
            TrackingMode::Piece, OwnershipModel::Consumable, RemovalStrategy::OffcutFirst,
            false, false, null, false,
        );

        $this->assertArrayHasKey('base_uom_id', $pelanggaran);

        // Satuan yang belum diketahui tidak diperiksa.
        $belumTahu = $this->matriks->violations(
            TrackingMode::Piece, OwnershipModel::Consumable, RemovalStrategy::OffcutFirst,
            false, false, null, null,
        );

        $this->assertArrayNotHasKey('base_uom_id', $belumTahu);
    }

    #[Test]
    public function br_cnv_03_item_bisa_dipotong_wajib_punya_panjang_minimum(): void
    {
        foreach ([null, 0.0, -1.0] as $nilai) {
            $pelanggaran = $this->matriks->violations(
                TrackingMode::Piece, OwnershipModel::Consumable, RemovalStrategy::OffcutFirst,
                false, true, $nilai, true,
            );

            $this->assertArrayHasKey('min_offcut_length', $pelanggaran);
        }
    }

    #[Test]
    public function br_mst_01_kode_master_dibakukan_huruf_besar(): void
    {
        $this->assertSame('KL-SATU', MasterCode::normalize('  kl satu  '));
        $this->assertSame('PRJ-001', MasterCode::normalize('prj-001'));
        // Spasi menjadi strip; karakter lain dibuang.
        $this->assertSame('ABC1-2-3', MasterCode::normalize('a/b*c#1 2 3'));
        $this->assertSame('', MasterCode::normalize('///'));
    }

    #[Test]
    public function br_wh_01_segmen_kode_bin_dibakukan(): void
    {
        $this->assertSame('B05', BinCodeBuilder::segment('b 05'));
        $this->assertSame('R03', BinCodeBuilder::segment('r-03'));
        $this->assertSame('A', BinCodeBuilder::segment(' a '));
        $this->assertSame('', BinCodeBuilder::segment('---'));
    }
}
