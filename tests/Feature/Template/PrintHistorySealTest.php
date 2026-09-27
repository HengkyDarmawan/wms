<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\PrintLog;
use App\Domain\Template\Models\SignatureSeal;
use App\Domain\Template\Support\DocumentPrinter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-22 s.d. TC-TPL-25 — riwayat cetak & tanda "CETAK ULANG" (A-263),
 * segel tanda tangan ber-QR dengan halaman verifikasi publik dan tanda
 * tangan yang digambar di profil (A-264).
 */
class PrintHistorySealTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    private Shipment $sj;

    private StockAdjustment $adj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 50]]);
        $this->qc($grn, 0, QcResult::Passed, false);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        $proyek = $this->makeProject();
        $req = $this->reqDisetujui($proyek, $this->gudang, $this->kabel, 30);
        $pck = $this->pckSelesai($req);
        $this->sj = $this->buktiTerima($this->sjBerangkat($pck, ['destination_type' => 'project_client', 'destination_project_id' => $proyek->id]), 30, 0);

        $this->adj = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => $this->gudang->id,
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->value('id'),
            'notes' => 'uji segel',
        ], [['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 2]], $this->makeUser('warehouse_staff', attributes: ['name' => 'Staf Penanda']));
    }

    #[Test]
    public function tc_tpl_22_setiap_cetak_dicatat_dan_cetak_ulang_ditandai(): void
    {
        $admin = $this->makeUser('company_admin');

        foreach ([1, 2, 3] as $n) {
            $this->actingAs($admin)->get($this->tenantUrl('print/shipment/'.$this->sj->id))->assertOk();
        }
        $this->actingAs($admin)->get($this->tenantUrl('print/proof-of-delivery/'.$this->sj->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/stock-adjustment/'.$this->adj->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/stock-adjustment/'.$this->adj->id))->assertOk();

        $sj = PrintLog::query()->where('document_type', 'shipment')->where('document_id', $this->sj->id)->orderBy('id')->get();
        $this->assertSame([1, 2, 3], $sj->pluck('copy_no')->all());
        $this->assertTrue($sj->every(fn (PrintLog $l) => (int) $l->printed_by === $admin->id && $l->document_number === $this->sj->number));
        $this->assertSame(1, PrintLog::query()->where('document_type', 'proof_of_delivery')->sole()->copy_no, 'Nomor cetakan per jenis cetak.');

        $printer = app(DocumentPrinter::class);
        $ulang = $printer->view(DocumentTemplateType::Shipment, $this->sj, 3)->render();
        $this->assertStringContainsString('CETAK ULANG ke-3', $ulang);
        $this->assertStringContainsString('cetakan ke-3', $ulang);
        $this->assertStringNotContainsString('CETAK ULANG', $printer->view(DocumentTemplateType::Shipment, $this->sj, 1)->render(), 'Cetakan pertama tanpa tanda.');

        $adj = $printer->view(DocumentTemplateType::StockAdjustment, $this->adj, 2)->render();
        $this->assertStringNotContainsString('CETAK ULANG', $adj, 'ADJ tidak ditandai besar.');
        $this->assertStringContainsString('cetakan ke-2', $adj);

        // Cetak ditolak (tanpa izin lihat) tidak tercatat.
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/stock-adjustment/'.$this->adj->id))->assertForbidden();
        $this->assertSame(2, PrintLog::query()->where('document_type', 'stock_adjustment')->count());
    }

    #[Test]
    public function tc_tpl_23_riwayat_cetak_tampil_di_layar_detail(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('print/shipment/'.$this->sj->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/shipment/'.$this->sj->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/proof-of-delivery/'.$this->sj->id))->assertOk();

        $this->actingAs($admin)->get($this->tenantUrl('shipments/'.$this->sj->id))->assertOk()
            ->assertSee('Riwayat cetak')
            ->assertSee('3 kali')
            ->assertSee('cetak ulang')
            ->assertSee('Bukti Terima');

        $this->actingAs($admin)->get($this->tenantUrl('adjustments/'.$this->adj->id))->assertOk()->assertDontSee('Riwayat cetak');
    }

    #[Test]
    public function tc_tpl_24_segel_tanda_tangan_dan_halaman_verifikasi_publik(): void
    {
        $printer = app(DocumentPrinter::class);
        $html = $printer->view(DocumentTemplateType::StockAdjustment, $this->adj)->render();

        $segel = SignatureSeal::query()->where('document_type', 'stock_adjustment')->where('document_id', $this->adj->id)->sole();
        $this->assertSame('Staf Penanda', $segel->signer_name);
        $this->assertSame('Diajukan', $segel->block_label);
        $this->assertSame(1, $segel->block_no);
        $this->assertSame($this->adj->created_at->toDateTimeString(), $segel->acted_at->toDateTimeString(), 'Waktu tindakan = saat diajukan.');
        $this->assertSame(40, strlen($segel->token));
        $this->assertStringContainsString('segel '.$segel->shortCode(), $html);
        $this->assertStringContainsString('pindai QR untuk verifikasi', $html);

        // Cetak ulang memakai segel yang sama; kotak tanpa pelaku tanpa segel.
        $printer->view(DocumentTemplateType::StockAdjustment, $this->adj->refresh())->render();
        $this->assertSame(1, SignatureSeal::query()->count());

        // Halaman verifikasi publik, tanpa login.
        Auth::logout();
        $this->get($this->tenantUrl('verifikasi/'.$segel->token))->assertOk()
            ->assertSee('Tanda tangan tercatat di sistem')
            ->assertSee($this->adj->number)
            ->assertSee('BA Penyesuaian Stok')
            ->assertSee('Staf Penanda')
            ->assertSee($segel->shortCode())
            ->assertSee('utuh')
            ->assertDontSee('BAUT-M12', false);

        $this->get($this->tenantUrl('verifikasi/'.str_repeat('x', 40)))->assertNotFound()->assertSee('Segel tidak dikenal');

        // Baris segel diubah diam-diam → sidik tidak cocok.
        SignatureSeal::query()->whereKey($segel->id)->update(['signer_name' => 'Orang Lain']);
        $this->get($this->tenantUrl('verifikasi/'.$segel->token))->assertOk()->assertSee('Data segel tidak cocok');

        // Dokumen dibatalkan → peringatan.
        SignatureSeal::query()->whereKey($segel->id)->update(['signer_name' => 'Staf Penanda']);
        StockAdjustment::query()->whereKey($this->adj->id)->update(['status' => 'cancelled']);
        $this->get($this->tenantUrl('verifikasi/'.$segel->token))->assertOk()->assertSee('Dokumen ini sudah dibatalkan');
    }

    #[Test]
    public function tc_tpl_25_tanda_tangan_digambar_di_profil(): void
    {
        Storage::fake('local');
        $user = $this->makeUser('warehouse_staff');

        // PNG 2×1 piksel sebagai hasil kanvas.
        $gambar = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($gambar);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $this->actingAs($user)->get($this->tenantUrl('profile'))->assertOk()->assertSee('Atau gambar tanda tangan di sini');
        $this->actingAs($user)->post($this->tenantUrl('profile/signature'), ['signature_data' => $png])->assertRedirect();
        $path = $user->refresh()->signature_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->post($this->tenantUrl('profile/signature'), ['signature_data' => 'data:image/png;base64,bukan-gambar'])
            ->assertSessionHasErrors('signature_data');
        $this->assertSame($path, $user->refresh()->signature_path, 'Isian rusak tidak mengganti tanda tangan.');
    }
}
