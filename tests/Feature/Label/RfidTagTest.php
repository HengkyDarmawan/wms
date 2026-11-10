<?php

declare(strict_types=1);

namespace Tests\Feature\Label;

use App\Domain\Label\Actions\SaveRfidTag;
use App\Domain\Label\Livewire\RfidTagManager;
use App\Domain\Label\Models\RfidTag;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-RFID-01 s.d. TC-RFID-04 — tag RFID mode keyboard wedge (A-437): EPC
 * diterjemahkan di ScanCode & BinCode, tag tidak dihapus, layar bergantung fitur.
 */
class RfidTagTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    private function tag(string $epc, string $jenis, string $kode): RfidTag
    {
        return app(SaveRfidTag::class)->handle(null, ['epc' => $epc, 'target_type' => $jenis, 'target_code' => $kode], $this->makeUser('warehouse_head'));
    }

    #[Test]
    public function tc_rfid_01_epc_barang_dan_bin_diterjemahkan(): void
    {
        $this->tag('e200 3412-0123', 'item', $this->baut->code);
        $this->tag('E2003412AAAA0001', 'bin', $this->binA->code);

        $this->assertSame([(int) $this->baut->id], array_column(ScanCode::resolve('E20034120123'), 'item_id'), 'Spasi/tanda hubung dibuang, huruf besar.');
        $this->assertSame($this->binA->code, BinCode::dariPindai('e2003412aaaa0001'));
        $this->assertSame([(int) $this->baut->id], array_column(ScanCode::resolve($this->baut->code), 'item_id'), 'Kode biasa tetap jalan.');
    }

    #[Test]
    public function tc_rfid_02_tag_nonaktif_dan_kode_pendek_diabaikan(): void
    {
        $t = $this->tag('E2003412BBBB0002', 'item', $this->baut->code);
        app(SaveRfidTag::class)->handle($t, ['epc' => $t->epc, 'target_type' => 'item', 'target_code' => $this->baut->code, 'is_active' => false], $this->makeUser('warehouse_head'));

        $this->assertSame([], ScanCode::resolve('E2003412BBBB0002'));
        $this->assertNull(RfidTag::aktif('ABC123'), 'Di bawah 8 digit bukan EPC.');
        $this->assertSame(1, RfidTag::query()->count(), 'Tag tidak dihapus (P-03).');
    }

    #[Test]
    public function tc_rfid_03_validasi_dan_izin(): void
    {
        $this->tag('E2003412CCCC0003', 'item', $this->baut->code);

        foreach ([['epc' => 'ZZZZZZZZZ', 'target_type' => 'item', 'target_code' => $this->baut->code], ['epc' => 'E2003412CCCC0003', 'target_type' => 'item', 'target_code' => $this->baut->code], ['epc' => 'E2003412DDDD0004', 'target_type' => 'bin', 'target_code' => 'TIDAK-ADA']] as $data) {
            try {
                app(SaveRfidTag::class)->handle(null, $data, $this->makeUser('warehouse_head'));
                $this->fail('Seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $this->expectException(AuthorizationException::class);
        app(SaveRfidTag::class)->handle(null, ['epc' => 'E2003412EEEE0005', 'target_type' => 'item', 'target_code' => $this->baut->code], $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_rfid_04_layar_bergantung_fitur(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        Livewire::actingAs($kepala)->test(RfidTagManager::class)->assertNotFound();

        FeatureFlag::query()->updateOrCreate(['company_id' => $this->company->getTenantKey(), 'key' => 'rfid'], ['enabled' => true]);
        Livewire::actingAs($kepala)->test(RfidTagManager::class)
            ->set('form.epc', 'E2003412FFFF0006')->set('form.target_type', 'item')->set('form.target_code', $this->baut->code)
            ->call('simpan')->assertHasNoErrors()->assertSet('form.epc', '')->assertSet('form.target_code', $this->baut->code)
            ->set('uji', 'E2003412FFFF0006')->call('ujiPindai')->assertSee('Tag E2003412FFFF0006');
    }
}
