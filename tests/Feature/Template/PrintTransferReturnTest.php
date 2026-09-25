<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Transfer\Models\Transfer;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-15 — Surat Transfer dan Bukti Retur tercetak (A-232): route 200,
 * nomor & baris tampil, tanpa nilai uang (D-07), izin lihat berlaku.
 */
class PrintTransferReturnTest extends TenantTestCase
{
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    #[Test]
    public function tc_tpl_15_surat_transfer_dan_bukti_retur_tercetak(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]]);

        $this->stok($this->binKrw1, $this->baut, 12);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 4]]);

        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('print/transfer/'.$trf->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/goods-return/'.$ret->id))->assertOk();

        $surat = app(DocumentPrinter::class)->view(DocumentTemplateType::Transfer, Transfer::query()->findOrFail($trf->id))->render();
        $this->assertStringContainsString($trf->number, $surat);
        $this->assertStringContainsString('BAUT-M12', $surat);
        $this->assertStringContainsString('Gudang Cabang Bekasi', $surat);
        $this->tanpaHarga($surat);

        $bukti = app(DocumentPrinter::class)->view(DocumentTemplateType::GoodsReturn, GoodsReturn::query()->findOrFail($ret->id))->render();
        $this->assertStringContainsString($ret->number, $bukti);
        $this->assertStringContainsString('BAUT-M12', $bukti);
        $this->assertStringContainsString($this->proyek->name, $bukti);
        $this->assertStringContainsString('Di Gudang Site', $bukti);
        $this->tanpaHarga($bukti);

        // Layar detail menautkan cetakan; klien tanpa akses transfer ditolak.
        $this->actingAs($admin)->get($this->tenantUrl('transfers/'.$trf->id))->assertOk()
            ->assertSee(route('print.document', ['type' => 'transfer', 'id' => $trf->id]));
        $this->actingAs($admin)->get($this->tenantUrl('returns/'.$ret->id))->assertOk()
            ->assertSee(route('print.document', ['type' => 'goods-return', 'id' => $ret->id]));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/transfer/'.$trf->id))->assertForbidden();
    }

    private function tanpaHarga(string $html): void
    {
        // Gambar QR/logo base64 dibuang dulu: urutan acaknya (bergantung id dokumen) bisa memuat "Rp".
        $html = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);

        foreach (['Rp', 'harga', 'Harga', 'Total', 'total harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $html, 'Cetakan tidak boleh memuat nilai uang (D-07).');
        }
    }
}
