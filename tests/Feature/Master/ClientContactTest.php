<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\DeactivateClientContact;
use App\Domain\Master\Actions\SaveClientContact;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\ClientContact;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Support\LegacyClientContacts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-40 dan TC-MST-41 — PIC Klien ([A-326]): banyak PIC per klien, nomor WA
 * dibakukan, nonaktif memakai Alasan (BR-GEN-11), dan isi balik kontak lama.
 */
class ClientContactTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_40_pic_klien_ditambah_diubah_dan_dinonaktifkan(): void
    {
        $klien = $this->makeClient();
        $proyekA = $this->makeProject(['client_id' => $klien->id]);
        $proyekB = $this->makeProject(['client_id' => $klien->id]);

        $sari = app(SaveClientContact::class)->handle($klien, null, [
            'name' => 'Sari Wulan',
            'position' => 'Admin Site',
            'phone' => '0812-3456-7890',
            'email' => 'Sari@Klien-Satu.test',
            'projects' => [$proyekA->id],
        ]);

        $this->assertSame('6281234567890', $sari->phone, 'Nomor WA dibakukan 62… seperti isian driver (A-315).');
        $this->assertSame('sari@klien-satu.test', $sari->email);
        $this->assertTrue($sari->is_active);
        $this->assertSame([$proyekA->id], $sari->projects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all());

        $budi = app(SaveClientContact::class)->handle($klien, null, [
            'name' => 'Budi Hartono',
            'position' => 'Project Manager',
            'projects' => [$proyekA->id, $proyekB->id],
        ]);

        $this->assertSame(2, ClientContact::query()->where('client_id', $klien->id)->count());
        $this->assertCount(2, $budi->projects()->get());

        // Mengubah: proyek yang diurus disinkronkan, bukan ditumpuk.
        app(SaveClientContact::class)->handle($klien, $budi, [
            'name' => 'Budi Hartono',
            'projects' => [$proyekB->id],
        ]);

        $this->assertSame([$proyekB->id], $budi->refresh()->projects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all());

        // Nomor yang jelas bukan HP ditolak di kolomnya sendiri.
        try {
            app(SaveClientContact::class)->handle($klien, null, ['name' => 'Tono', 'phone' => '123']);
            $this->fail('Nomor WA tidak sah seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('phone', $e->fieldErrors);
        }

        // Nama wajib (BR-GEN-11).
        try {
            app(SaveClientContact::class)->handle($klien, null, ['name' => '   ']);
            $this->fail('Nama PIC kosong seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('name', $e->fieldErrors);
        }

        // Proyek milik klien lain tidak boleh dipilih.
        $proyekLain = $this->makeProject();

        try {
            app(SaveClientContact::class)->handle($klien, null, ['name' => 'Tono', 'projects' => [$proyekLain->id]]);
            $this->fail('Proyek klien lain seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('projects', $e->fieldErrors);
        }

        // Nonaktif menuntut Alasan (BR-GEN-11).
        try {
            app(DeactivateClientContact::class)->handle($sari, '');
            $this->fail('Nonaktif tanpa alasan seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $alasan = ReasonCode::create([
            'context' => 'cancel',
            'code' => 'PIC-PINDAH',
            'label' => 'Pindah tugas',
            'is_active' => true,
        ]);

        app(DeactivateClientContact::class)->handle($sari, $alasan->code, 'Kembali ke kantor pusat');

        $this->assertFalse($sari->refresh()->is_active);
        $this->assertDatabaseHas('client_contacts', ['id' => $sari->id], 'tenant');

        app(DeactivateClientContact::class)->reactivate($sari);

        $this->assertTrue($sari->refresh()->is_active);
    }

    #[Test]
    public function tc_mst_41_kontak_klien_lama_menjadi_pic_pertama(): void
    {
        $berkontak = $this->makeClient([
            'contact_name' => 'Pak Rudi',
            'phone' => '0811-1111-111',
            'email' => 'rudi@klien-lama.test',
        ]);

        $tanpaNama = $this->makeClient(['phone' => '0899-9999-999']);
        $kosong = $this->makeClient();

        LegacyClientContacts::isiBalik();

        $pic = ClientContact::query()->where('client_id', $berkontak->id)->firstOrFail();

        $this->assertSame('Pak Rudi', $pic->name);
        $this->assertSame('0811-1111-111', $pic->phone, 'Nilai lama disalin apa adanya; pembakuan baru terjadi saat disimpan ulang.');
        $this->assertSame('rudi@klien-lama.test', $pic->email);

        $tanpaNamaPic = ClientContact::query()->where('client_id', $tanpaNama->id)->firstOrFail();
        $this->assertSame('Kontak '.$tanpaNama->name, $tanpaNamaPic->name);

        $this->assertSame(0, ClientContact::query()->where('client_id', $kosong->id)->count(), 'Klien tanpa kontak lama tidak dibuatkan PIC.');

        // P-03: kolom lama tidak dihapus.
        $this->assertSame('Pak Rudi', Client::query()->findOrFail($berkontak->id)->contact_name);

        // Aman diulang.
        LegacyClientContacts::isiBalik();

        $this->assertSame(1, ClientContact::query()->where('client_id', $berkontak->id)->count());
    }
}
