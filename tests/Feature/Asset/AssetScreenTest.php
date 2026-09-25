<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Livewire\AssetDetail;
use App\Domain\Asset\Livewire\AssetList;
use App\Domain\Asset\Livewire\HandoverDetail;
use App\Domain\Asset\Livewire\HandoverList;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\TenantTestCase;

/**
 * TC-AST-10 s.d. TC-AST-13 — izin layar, cakupan & menu (BR-GEN-09,
 * BR-ACC-05), layar serah terima + pemeriksaan lewat POST dengan foto, detail
 * aset (profil, tandai hilang), cetak BA Serah Terima Aset tanpa harga (D-07).
 */
class AssetScreenTest extends TenantTestCase
{
    use AssetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();
        $this->pinjamkan();
    }

    private function kodeAlasan(ReasonContext $context): string
    {
        return (string) ReasonCode::query()->where('context', $context->value)->value('code');
    }

    #[Test]
    public function tc_ast_10_izin_layar_cakupan_dan_menu(): void
    {
        $ast = $this->ast();
        $staf = $this->makeUser('warehouse_staff');

        foreach (['assets', 'assets/'.$this->gns->id, 'asset-handovers', 'asset-handovers/'.$ast->id, 'reports/aset-dipinjamkan'] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }
        $this->actingAs($staf)->get($this->tenantUrl('assets'))->assertSee('GNS-01');
        $this->actingAs($staf)->get($this->tenantUrl('asset-handovers'))->assertSee($ast->number);

        $this->actingAs($this->makeUser('external_auditor'))->get($this->tenantUrl('assets/'.$this->gns->id))->assertOk();
        foreach (['internal_requester', 'driver', 'pr_follow_up'] as $role) {
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('assets'))->assertForbidden();
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('asset-handovers'))->assertForbidden();
        }

        // BR-ACC-05: gudang lain tidak melihat aset & AST CKG.
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->bks->id);
        $this->actingAs($stafBks)->get($this->tenantUrl('asset-handovers/'.$ast->id))->assertNotFound();
        $this->actingAs($stafBks)->get($this->tenantUrl('assets/'.$this->gns->id))->assertNotFound();
        $this->actingAs($stafBks)->get($this->tenantUrl('assets'))->assertOk()->assertDontSee('GNS-01');

        // Serial bukan aset tidak punya halaman aset.
        $kabel = Serial::create(['item_id' => $this->kabel->id, 'serial_no' => 'KBL-9']);
        $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('assets/'.$kabel->id))->assertNotFound();

        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(route('assets.index'))->assertSee(route('asset-handovers.index'));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()->assertDontSee(route('assets.index'));
    }

    #[Test]
    public function tc_ast_11_layar_serah_terima_pemeriksaan_dan_detail_aset(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');
        $ast = $this->ast();

        Livewire::actingAs($kepala)->test(HandoverList::class)->assertOk()->assertSee($ast->number)
            ->set('overdue', true)->assertDontSee($ast->number);

        Livewire::actingAs($kepala)->test(HandoverDetail::class, ['assetHandover' => $ast])
            ->assertSee(__('Lengkapi serah terima'))
            ->call('mulaiUbah')
            ->set('form.due_return_date', now()->subYear()->toDateString())
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-AST-06')
            ->set('form.due_return_date', now()->addDays(7)->toDateString())
            ->set('form.meter_out', '101')
            ->call('simpan')
            ->assertSet('ruleError', '');
        $this->assertSame(101.0, (float) $ast->refresh()->meter_out);

        Livewire::actingAs($staf)->test(HandoverDetail::class, ['assetHandover' => $ast])->assertDontSee(__('Lengkapi serah terima'));

        // Kembali, lalu diperiksa lewat form POST dengan foto.
        $ret = $this->kembalikan();
        Livewire::actingAs($staf)->test(HandoverDetail::class, ['assetHandover' => $ast])
            ->assertSee(route('asset-handovers.inspect', $ast));

        $url = $this->tenantUrl('asset-handovers/'.$ast->id.'/inspect');
        $this->actingAs($staf)->post($url, ['condition_grade' => 'B', 'condition_score' => 80, 'component_notes' => 'Mesin: normal', 'meter_in' => 130])
            ->assertRedirect()->assertSessionHasErrors('photo');
        $this->actingAs($this->makeUser('external_auditor'))->post($url, [])->assertForbidden();

        $this->actingAs($staf)->post($url, [
            'condition_grade' => 'B', 'condition_score' => 80, 'component_notes' => "Mesin: normal\nKabel: baik", 'meter_in' => 130,
            'photo' => UploadedFile::fake()->image('ba.png'),
        ])->assertRedirect(route('asset-handovers.show', $ast));

        $ast->refresh();
        $this->assertSame(AssetHandoverStatus::Inspected, $ast->status);
        $periksa = $ast->inspections()->sole();
        $this->actingAs($staf)->get($this->tenantUrl('asset-inspections/'.$periksa->id.'/photo'))->assertOk();
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('asset-inspections/'.$periksa->id.'/photo'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('asset-handovers/'.$ast->id))->assertOk()->assertSee(__('Hasil pemeriksaan'))->assertSee('80 %');

        $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]]);

        // Detail aset: profil lalu tandai hilang lewat dialog.
        Livewire::actingAs($kepala)->test(AssetList::class)->assertOk()->assertSee('GNS-01')->assertSee(AssetState::Available->label());

        Livewire::actingAs($kepala)->test(AssetDetail::class, ['serial' => $this->gns])
            ->assertSee($ast->number)
            ->assertSee(__('Riwayat kondisi'))
            ->call('mintaDialog', 'profil')
            ->set('form.expected_life_days', '-1')
            ->call('simpanProfil')
            ->assertSet('ruleCode', 'BR-AST-08')
            ->set('form.expected_life_days', '1500')
            ->call('simpanProfil')
            ->assertSet('ruleError', '')
            ->call('mintaDialog', 'hilang')
            ->call('tandaiHilang')
            ->assertHasErrors('form.reason')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Lost))
            ->call('tandaiHilang')
            ->assertSet('ruleError', '');

        $this->gns->refresh();
        $this->assertSame(1500, $this->gns->expected_life_days);
        $this->assertSame(AssetState::Lost, $this->gns->asset_state);

        Livewire::actingAs($staf)->test(AssetDetail::class, ['serial' => $this->gns])
            ->assertDontSee(__('Ubah profil masa pakai'))->assertDontSee(__('Ditemukan kembali'));
    }

    #[Test]
    public function tc_ast_12_cetak_ba_serah_terima_aset(): void
    {
        $ast = $this->ast();
        $staf = $this->makeUser('warehouse_staff');

        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::AssetHandover, $ast)->render();
        $this->assertStringContainsString($ast->number, $html);
        $this->assertStringContainsString('BA Serah Terima Aset', $html);
        $this->assertStringContainsString('GNS-01', $html);
        $this->assertStringContainsString('Dikembalikan', $html, 'Blok tanda tangan Diserahkan · Diterima · Dikembalikan.');
        $teks = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);
        foreach (['Rp', 'harga', 'Harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $teks, 'D-07.');
        }

        $this->actingAs($staf)->get($this->tenantUrl('print/asset-handover/'.$ast->id))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/asset-handover/'.$ast->id))->assertForbidden();

        Livewire::actingAs($staf)->test(HandoverDetail::class, ['assetHandover' => $ast])
            ->assertSee(route('print.document', ['type' => 'asset-handover', 'id' => $ast->id]));
    }

    #[Test]
    public function tc_ast_13_foto_serah_terima_keluar_sebagai_lampiran(): void
    {
        $ast = $this->ast();
        $kepala = $this->makeUser('warehouse_head');
        $url = $this->tenantUrl('asset-handovers/'.$ast->id.'/photo-out');

        Livewire::actingAs($kepala)->test(HandoverDetail::class, ['assetHandover' => $ast])
            ->assertSee(__('Foto serah terima keluar'))->assertSee(route('asset-handovers.photo-out', $ast));

        $this->actingAs($kepala)->post($url, ['photo_out' => UploadedFile::fake()->image('keluar-1.jpg')])->assertRedirect(route('asset-handovers.show', $ast));
        $pertama = (int) $ast->refresh()->photo_out_id;
        $this->actingAs($kepala)->post($url, ['photo_out' => UploadedFile::fake()->image('keluar-2.jpg')])->assertSessionHasNoErrors();

        // Foto baru menggantikan rujukan; yang lama tetap tersimpan (P-03).
        $ast->refresh();
        $this->assertNotSame($pertama, (int) $ast->photo_out_id);
        $this->assertSame(2, Attachment::query()->for($ast)->count());
        $this->actingAs($kepala)->get($this->tenantUrl('attachments/'.$ast->photo_out_id))->assertOk();

        // Tanpa asset.manage ditolak; setelah kembali tidak bisa lagi.
        $this->actingAs($this->makeUser('warehouse_staff'))->post($url, ['photo_out' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
        $this->kembalikan();
        $this->actingAs($kepala)->post($url, ['photo_out' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
    }
}
