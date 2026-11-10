<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Count\Models\CountAssignment;
use App\Domain\Shared\Livewire\SyncReview;
use App\Domain\Shared\Models\SyncItem;
use App\Domain\Shared\Support\OfflineSync;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-PWA-11 s.d. TC-PWA-15 — hitung opname offline & sinkron (32-pwa-offline,
 * AD-16, A-438): idempoten, bentrok → antrean tinjauan, hitung buta.
 */
class OfflineSyncTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    private function tugasStaf1(): CountAssignment
    {
        $sesi = $this->sesiBerjalan(['bin_ids' => [$this->binA->id], 'team_user_ids' => [$this->staf1->id]]);

        return CountAssignment::query()->where('stock_count_id', $sesi->id)->where('counter_user_id', $this->staf1->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function item(CountAssignment $t, bool $selesai = true, ?string $uuid = null): array
    {
        $qtys = $t->linesQuery()->get()->mapWithKeys(fn ($l) => [$l->id => $l->serial_id || $l->piece_id ? '1' : '100'])->all();

        return ['uuid' => $uuid ?? (string) Str::uuid(), 'kind' => 'count_record', 'recorded_at' => now()->toIso8601String(), 'payload' => ['assignment_id' => $t->id, 'qtys' => $qtys, 'finish' => $selesai]];
    }

    #[Test]
    public function tc_pwa_11_12_isian_diposting_sekali_walau_dikirim_ulang(): void
    {
        $t = $this->tugasStaf1();
        $kirim = $this->item($t);

        $h1 = app(OfflineSync::class)->handle($this->staf1, [$kirim]);
        $this->assertSame('posted', $h1[0]['status']);
        $this->assertTrue($t->refresh()->isDone(), 'Selesai = penugasan ditutup lewat RecordCount.');

        $h2 = app(OfflineSync::class)->handle($this->staf1, [$kirim]);
        $this->assertSame('posted', $h2[0]['status'], 'Kirim ulang UUID sama: status lama, tidak diproses ulang.');
        $this->assertSame(1, SyncItem::query()->count());
    }

    #[Test]
    public function tc_pwa_13_bentrok_masuk_tinjauan_lalu_diputus(): void
    {
        $t = $this->tugasStaf1();
        // Bin sudah diselesaikan (mis. dari layar online), lalu HP yang tadinya offline mengirim isian lagi.
        app(OfflineSync::class)->handle($this->staf1, [$this->item($t)]);
        $h = app(OfflineSync::class)->handle($this->staf1, [$this->item($t)]);
        $this->assertSame('review', $h[0]['status'], 'Bentrok tidak diposting otomatis (BR-OPN-03).');
        $this->assertStringContainsString('sudah selesai dihitung', (string) $h[0]['message']);

        $item = SyncItem::query()->where('status', 'review')->sole();
        Livewire::actingAs($this->kepala)->test(SyncReview::class)
            ->assertSee('Hitung opname')->assertSee('Perlu ditinjau')
            ->call('cobaLagi', $item->id)
            ->call('tolak', $item->id);
        $this->assertSame('rejected', $item->refresh()->status);
        $this->assertSame((int) $this->kepala->id, (int) $item->decided_by);
    }

    #[Test]
    public function tc_pwa_14_data_rusak_ditolak(): void
    {
        $h = app(OfflineSync::class)->handle($this->staf1, [
            ['uuid' => 'bukan-uuid', 'kind' => 'count_record', 'payload' => []],
            ['uuid' => (string) Str::uuid(), 'kind' => 'tidak_dikenal', 'payload' => []],
            ['uuid' => (string) Str::uuid(), 'kind' => 'count_record', 'payload' => ['assignment_id' => 999999]],
        ]);

        $this->assertSame(['rejected', 'rejected', 'rejected'], array_column($h, 'status'));
    }

    #[Test]
    public function tc_pwa_15_endpoint_data_hitung_buta_dan_sinkron(): void
    {
        $t = $this->tugasStaf1();

        $data = $this->actingAs($this->staf1)->getJson($this->tenantUrl('offline/hitung/data'))->assertOk()->json();
        $this->assertSame($t->id, $data['tugas'][0]['id']);
        $this->assertArrayNotHasKey('system_qty', $data['tugas'][0]['baris'][0], 'Hitung buta: angka sistem tidak dikirim.');
        $this->assertStringNotContainsString('system', json_encode($data));

        $this->actingAs($this->staf1)->get($this->tenantUrl('offline/hitung'))->assertOk()->assertSee('data-offline-count', false);
        $this->actingAs($this->staf1)->postJson($this->tenantUrl('offline/sync'), ['items' => [$this->item($t)]])
            ->assertOk()->assertJsonPath('hasil.0.status', 'posted');

        $this->actingAs($this->makeUser('internal_requester'))->get($this->tenantUrl('offline/hitung'))->assertForbidden();
        $this->actingAs($this->staf1)->get($this->tenantUrl('offline/tinjauan'))->assertForbidden();
    }
}
