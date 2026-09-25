<?php

declare(strict_types=1);

namespace Tests\Unit\Conversion;

use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Support\ConversionPlanner;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TC-CNV-15 — perencana konversi (A-229): Potong menghitung kerf dari master,
 * sisa menjadi offcut/waste, melebihi batang ditolak; Ganti kemasan menghitung
 * susut; Rakit/Bongkar tanpa neraca. Murni, tanpa basis data.
 */
class ConversionPlannerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function batang(float $panjang = 6, ?float $kerf = 0.005, ?float $min = 0.5): array
    {
        return ['key' => '1:10:0:7', 'item_id' => 10, 'item_code' => 'PIPA', 'piece_id' => 7, 'lot_id' => null, 'bin_id' => 1,
            'base_uom_id' => 3, 'uom' => 'M', 'rounding' => null, 'balance' => $panjang, 'qty' => $panjang, 'kerf' => $kerf, 'min_offcut' => $min];
    }

    private function items(): Collection
    {
        return collect([
            10 => (object) ['id' => 10, 'code' => 'PIPA', 'base_uom_id' => 3],
            11 => (object) ['id' => 11, 'code' => 'PIPA-PENDEK', 'base_uom_id' => 3],
            12 => (object) ['id' => 12, 'code' => 'BAUT', 'base_uom_id' => 1],
        ]);
    }

    #[Test]
    public function potong_menghitung_kerf_sisa_offcut_dan_kalimat(): void
    {
        $r = (new ConversionPlanner)->plan(ConversionType::Cut, [$this->batang()], [
            ['id' => 'a', 'item_id' => '', 'length' => '2.5', 'count' => '2'],
        ], $this->items(), ['bin_id' => '', 'offcut_reason_id' => 5]);

        $this->assertSame([], $r['errors']);
        $this->assertSame(5.0, $r['summary']['dipakai']);
        $this->assertSame(0.01, $r['summary']['kerf']);
        $this->assertSame(0.99, $r['summary']['sisa']);
        $this->assertSame('offcut', $r['summary']['sisa_jenis']);
        $this->assertSame('6 M → 2 × 2,5 M + offcut 0,99 M + kerf 0,01 M', $r['summary']['kalimat']);
        $this->assertSame(['output', 'kerf', 'offcut'], array_column($r['outputs'], 'kind'));
        $this->assertSame('2', $r['outputs'][0]['count']);
        $this->assertSame('10', $r['outputs'][0]['item_id']);
        $this->assertNull($r['outputs'][2]['reason_code_id'], 'Offcut layak: tanpa alasan waste.');
    }

    #[Test]
    public function potong_sisa_di_bawah_minimum_menjadi_waste_dan_kerf_kosong_diperingatkan(): void
    {
        $r = (new ConversionPlanner)->plan(ConversionType::Cut, [$this->batang(6, null, 0.5)], [
            ['id' => 'a', 'item_id' => '11', 'length' => '2.9', 'count' => '2'],
        ], $this->items(), ['offcut_reason_id' => 5]);

        $this->assertSame([], $r['errors']);
        $this->assertSame(0.0, $r['summary']['kerf']);
        $this->assertSame(0.2, $r['summary']['sisa']);
        $this->assertSame('waste', $r['summary']['sisa_jenis']);
        $this->assertCount(1, $r['summary']['peringatan']);
        $this->assertSame(['output', 'offcut'], array_column($r['outputs'], 'kind'), 'Tetap dikirim offcut; server yang menurunkannya (BR-CNV-03).');
        $this->assertSame(5, $r['outputs'][1]['reason_code_id']);
        $this->assertStringContainsString('2 × 2,9 M PIPA-PENDEK + waste 0,2 M', $r['summary']['kalimat']);
    }

    #[Test]
    public function potong_menolak_melebihi_batang_satuan_beda_dan_tanpa_ukuran(): void
    {
        $p = new ConversionPlanner;

        $lebih = $p->plan(ConversionType::Cut, [$this->batang()], [['id' => 'a', 'item_id' => '', 'length' => '3', 'count' => '3']], $this->items());
        $this->assertArrayHasKey('potong', $lebih['errors']);
        $this->assertSame(-3.015, $lebih['summary']['sisa']);

        $beda = $p->plan(ConversionType::Cut, [$this->batang()], [['id' => 'a', 'item_id' => '12', 'length' => '1', 'count' => '1']], $this->items());
        $this->assertArrayHasKey('potong.a', $beda['errors']);

        $kosong = $p->plan(ConversionType::Cut, [$this->batang()], [['id' => 'a', 'item_id' => '', 'length' => '', 'count' => '1']], $this->items());
        $this->assertArrayHasKey('potong', $kosong['errors']);

        $tanpaBatang = $p->plan(ConversionType::Cut, [], [], $this->items());
        $this->assertArrayHasKey('batang', $tanpaBatang['errors']);

        // Habis pas: tanpa offcut, toleransi pembulatan.
        $pas = $p->plan(ConversionType::Cut, [$this->batang(6, 0.005, 0.5)], [['id' => 'a', 'item_id' => '', 'length' => '2.995', 'count' => '2']], $this->items());
        $this->assertSame([], $pas['errors']);
        $this->assertSame(['output', 'kerf'], array_column($pas['outputs'], 'kind'));
        $this->assertNull($pas['summary']['sisa_jenis']);
    }

    #[Test]
    public function ganti_kemasan_menghitung_susut_dan_menolak_hasil_melebihi_input(): void
    {
        $p = new ConversionPlanner;
        $input = ['key' => '1:12:0:0', 'item_id' => 12, 'item_code' => 'BAUT', 'piece_id' => null, 'lot_id' => null, 'bin_id' => 1, 'base_uom_id' => 1, 'uom' => 'PCS', 'rounding' => null, 'balance' => 100.0, 'qty' => 10.0];
        $items = $this->items()->put(13, (object) ['id' => 13, 'code' => 'BAUT-DUS', 'base_uom_id' => 1]);

        $r = $p->plan(ConversionType::Repack, [$input], [['id' => 'a', 'kind' => 'output', 'item_id' => '13', 'qty' => '9', 'bin_id' => '', 'lot_no' => '']], $items, ['spoil_reason_id' => 8]);
        $this->assertSame([], $r['errors']);
        $this->assertSame(1.0, $r['summary']['susut']);
        $this->assertSame(['output', 'waste'], array_column($r['outputs'], 'kind'));
        $this->assertSame(8, $r['outputs'][1]['reason_code_id']);
        $this->assertSame('10 PCS → 9 PCS hasil + susut 1 PCS (waste)', $r['summary']['kalimat']);

        $lebih = $p->plan(ConversionType::Repack, [$input], [['id' => 'a', 'kind' => 'output', 'item_id' => '13', 'qty' => '11']], $items);
        $this->assertArrayHasKey('hasil', $lebih['errors']);

        $bedaSatuan = $p->plan(ConversionType::Repack, [$input], [['id' => 'a', 'kind' => 'output', 'item_id' => '10', 'qty' => '1']], $items);
        $this->assertArrayHasKey('hasil.a', $bedaSatuan['errors']);
    }

    #[Test]
    public function rakit_dan_bongkar_tanpa_neraca(): void
    {
        $input = ['key' => '1:12:0:0', 'item_id' => 12, 'item_code' => 'BAUT', 'piece_id' => null, 'lot_id' => null, 'bin_id' => 1, 'base_uom_id' => 1, 'uom' => 'PCS', 'balance' => 100.0, 'qty' => 50.0];

        $r = (new ConversionPlanner)->plan(ConversionType::Assemble, [$input], [
            ['id' => 'a', 'kind' => 'output', 'item_id' => '10', 'qty' => '1', 'bin_id' => '', 'lot_no' => ''],
            ['id' => 'b', 'kind' => 'waste', 'item_id' => '', 'qty' => '2'],
        ], $this->items(), ['spoil_reason_id' => 8]);

        $this->assertSame([], $r['errors']);
        $this->assertSame(['output', 'waste'], array_column($r['outputs'], 'kind'));
        $this->assertSame('1:12:0:0', $r['outputs'][1]['parent']);
        $this->assertStringContainsString('tanpa neraca', $r['summary']['kalimat']);

        $kosong = (new ConversionPlanner)->plan(ConversionType::Disassemble, [$input], [['id' => 'a', 'kind' => 'output', 'item_id' => '', 'qty' => '']], $this->items());
        $this->assertArrayHasKey('hasil', $kosong['errors']);
    }
}
