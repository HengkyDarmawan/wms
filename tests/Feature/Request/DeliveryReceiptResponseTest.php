<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Request\Actions\RespondDeliveryReceipt;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\ResolveDiscrepancy;
use App\Domain\Shipment\Enums\DiscrepancyOrigin;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\ReceiptConfirmation;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Models\StockMovement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-REQ-30 s.d. TC-REQ-33 — pemohon menanggapi bukti terima: terima,
 * keberatan (DSC `client_dispute` tanpa pergerakan stok), dan konfirmasi
 * otomatis lewat batas (BR-REQ-10, A-63, A-188).
 */
class DeliveryReceiptResponseTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    private User $pemohon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    /** SJ jual-putus ke proyek sudah diterima baik 10 baut. */
    private function sjDiterima(): Shipment
    {
        $grn = app(CompleteGoodsReceipt::class)->handle($this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 10]]), $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        $proyek = $this->makeProject();
        $req = $this->reqDisetujui($proyek, $this->gudang, $this->baut, 10);
        $this->pemohon = User::query()->findOrFail($req->requester_id);

        $sj = $this->sjBerangkat($this->pckSelesai($req), ['destination_type' => 'project_client', 'destination_project_id' => $proyek->id]);

        return $this->buktiTerima($sj, 10);
    }

    private function url(Shipment $sj, string $aksi): string
    {
        $req = $sj->lines()->first()->pickTaskLine->pickTask->source_id;

        return $this->tenantUrl('requests/'.$req.'/receipts/'.$sj->proof->id.'/'.$aksi);
    }

    #[Test]
    public function tc_req_30_pemohon_mengonfirmasi_terima_dari_layar(): void
    {
        $sj = $this->sjDiterima();
        $req = $sj->lines()->first()->pickTaskLine->pickTask->source_id;

        $this->actingAs($this->pemohon)->get($this->tenantUrl('requests/'.$req))->assertOk()
            ->assertSee(__('Pengiriman & konfirmasi terima'))->assertSee(__('Ajukan keberatan'));

        $this->actingAs($this->pemohon)->post($this->url($sj, 'confirm'))->assertSessionHasNoErrors();
        $this->assertSame(ReceiptConfirmation::Confirmed, $sj->proof->refresh()->confirmation);
        $this->assertNotNull($sj->proof->requester_confirmed_at);

        // Sudah ditanggapi: kedua kalinya ditolak.
        $this->actingAs($this->pemohon)->post($this->url($sj, 'confirm'))->assertSessionHasErrors('delivery');

        // Staf gudang tidak memegang izin konfirmasi.
        $this->actingAs($this->makeUser('warehouse_staff'))->post($this->url($sj, 'confirm'))->assertForbidden();
    }

    #[Test]
    public function tc_req_31_keberatan_membuka_dsc_tanpa_memindah_stok(): void
    {
        Storage::fake('local');
        $sj = $this->sjDiterima();
        $baris = $sj->lines()->first();

        // Rusak tanpa foto ditolak; kurang + rusak melebihi yang diterima ditolak.
        $this->actingAs($this->pemohon)->post($this->url($sj, 'dispute'), ['lines' => [$baris->id => ['qty_damaged' => 1]]])->assertSessionHasErrors('delivery');
        $this->actingAs($this->pemohon)->post($this->url($sj, 'dispute'), ['lines' => [$baris->id => ['qty_missing' => 11]]])->assertSessionHasErrors('delivery');

        $this->actingAs($this->pemohon)->post($this->url($sj, 'dispute'), [
            'lines' => [$baris->id => ['qty_missing' => 2, 'qty_damaged' => 1]],
            'photos' => [$baris->id => UploadedFile::fake()->image('rusak.jpg')],
            'notes' => 'Dua batang tidak ada, satu penyok',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ReceiptConfirmation::Disputed, $sj->proof->refresh()->confirmation);
        $dsc = DeliveryDiscrepancy::query()->where('shipment_id', $sj->id)->sole();
        $this->assertSame(DiscrepancyOrigin::ClientDispute, $dsc->origin);
        $this->assertSame(2, $dsc->lines()->count());
        $this->assertNotNull($dsc->lines()->where('discrepancy_type', 'damaged')->value('photo_path'));

        // Penyelesaian mencatat disposisi tanpa pergerakan stok (barang sudah di tangan klien).
        $gerak = StockMovement::query()->count();
        app(ResolveDiscrepancy::class)->handle($dsc, $dsc->lines->map(fn ($l) => ['line_id' => $l->id, 'disposition' => 'claimed', 'client_decision' => 'not_needed', 'claim_ref' => 'KLM-01'])->all(), null, $this->makeUser('warehouse_head'));
        $this->assertSame(DiscrepancyStatus::Resolved, $dsc->refresh()->status);
        $this->assertSame($gerak, StockMovement::query()->count());
    }

    #[Test]
    public function tc_req_33_keberatan_masih_dibutuhkan_membuka_lagi_baris_req(): void
    {
        $sj = $this->sjDiterima();
        $baris = $sj->lines()->first();
        $req = MaterialRequest::query()->findOrFail($baris->pickTaskLine->pickTask->source_id);
        $this->assertSame(MaterialRequestStatus::Completed, $req->refresh()->status);

        $dsc = app(RespondDeliveryReceipt::class)->dispute($req, $sj->proof, [$baris->id => ['qty_missing' => 2]], [], null, $this->pemohon);
        $putus = fn () => app(ResolveDiscrepancy::class)->handle($dsc, $dsc->lines->map(fn ($l) => ['line_id' => $l->id, 'disposition' => 'reship', 'client_decision' => 'still_needed'])->all(), null, $this->makeUser('warehouse_head'));

        // REQ sudah Selesai (terminal): masih dibutuhkan ditolak, arahkan ke REQ baru (A-198).
        try {
            $putus();
            $this->fail('REQ selesai tidak boleh dibuka lagi.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-10', $e->rule);
        }

        // REQ masih berjalan (mis. baris lain belum terkirim): baris dibuka lagi untuk diambil ulang.
        $req->forceFill(['status' => MaterialRequestStatus::PartiallyFulfilled])->save();
        $putus();

        $garis = $req->lines()->sole();
        $this->assertSame(RequestLineStatus::Open, $garis->status);
        $this->assertEqualsWithDelta(8.0, (float) $garis->qty_received, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $garis->qty_backorder, 0.0001);
        $this->assertSame(MaterialRequestStatus::PartiallyFulfilled, $req->refresh()->status);
    }

    #[Test]
    public function tc_req_32_diam_sampai_batas_menjadi_konfirmasi_otomatis(): void
    {
        $sj = $this->sjDiterima();
        $this->assertSame(0, app(RespondDeliveryReceipt::class)->autoConfirm());

        $sj->proof->forceFill(['confirm_deadline_at' => now()->subMinute()])->save();
        $this->artisan('deliveries:auto-confirm')->assertSuccessful();
        $this->assertSame(ReceiptConfirmation::AutoConfirmed, $sj->proof->refresh()->confirmation);

        // Lewat batas: tanggapan tidak diterima lagi.
        $this->actingAs($this->pemohon)->post($this->url($sj, 'confirm'))->assertSessionHasErrors('delivery');
    }
}
