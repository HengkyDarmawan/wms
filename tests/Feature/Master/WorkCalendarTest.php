<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Enums\HolidayKind;
use App\Domain\Master\Livewire\HolidayCalendar;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Holiday;
use App\Domain\Master\Support\WorkCalendar;
use Carbon\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-28 — kalender libur & hari kerja (A-270): libur nasional/cuti
 * bersama terisi otomatis, bisa dinonaktifkan, libur company ditambah, dan
 * hari kerja per minggu dari pengaturan.
 */
class WorkCalendarTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_28_hari_kerja_dan_libur_otomatis(): void
    {
        $k = app(WorkCalendar::class);

        // Isi otomatis saat tahun 2026 pertama dipakai: 17 libur nasional + 8 cuti bersama.
        $this->assertTrue($k->isHoliday(Carbon::parse('2026-03-21')), 'Idulfitri 1447 H.');
        $this->assertSame(17, Holiday::query()->whereYear('date', 2026)->where('kind', HolidayKind::National->value)->count());
        $this->assertSame(8, Holiday::query()->whereYear('date', 2026)->where('kind', HolidayKind::JointLeave->value)->count());
        $this->assertSame([2026], CompanySetting::get(WorkCalendar::FILLED));

        // Bawaan Senin–Sabtu: Sabtu kerja, Minggu libur.
        $this->assertTrue($k->isWorkday(Carbon::parse('2026-10-10')), 'Sabtu biasa = hari kerja.');
        $this->assertFalse($k->isWorkday(Carbon::parse('2026-10-11')), 'Minggu.');

        // Mundur 1 hari kerja dari Rabu 25 Mar 2026: 24 & 23 cuti bersama, 22 & 21 Idulfitri,
        // 20 cuti bersama, 19 Nyepi, 18 cuti bersama → Selasa 17 Mar.
        $this->assertSame('2026-03-17', $k->subWorkdays(Carbon::parse('2026-03-25 09:00'), 1)->toDateString());

        // Senin–Jumat: Sabtu tidak dihitung.
        CompanySetting::put(WorkCalendar::SETTING, 5);
        $k2 = new WorkCalendar;
        $this->assertFalse($k2->isWorkday(Carbon::parse('2026-10-10')));
        $this->assertSame('2026-10-09', $k2->subWorkdays(Carbon::parse('2026-10-12 08:00'), 1)->toDateString());

        // Cuti bersama dinonaktifkan (company tetap bekerja) → hari kerja; tidak muncul lagi saat isi ulang.
        $admin = $this->makeUser('company_admin');
        $cuti = Holiday::query()->whereDate('date', '2026-12-24')->sole();

        Livewire::actingAs($admin)
            ->test(HolidayCalendar::class)
            ->assertSee('Cuti bersama Kelahiran Yesus Kristus')
            ->call('aturAktif', $cuti->id, false)
            ->call('isiNasional')
            ->set('form.date', '2026-12-31')
            ->set('form.name', 'Libur akhir tahun kantor')
            ->call('tambah')
            ->assertHasNoErrors()
            ->set('form.date', '2026-12-31')
            ->set('form.name', 'Ganda')
            ->call('tambah')
            ->assertHasErrors('form.date');

        $this->assertFalse($cuti->refresh()->is_active);
        $this->assertTrue((new WorkCalendar)->isWorkday(Carbon::parse('2026-12-24')));
        $this->assertFalse((new WorkCalendar)->isWorkday(Carbon::parse('2026-12-31')));
        $this->assertSame(HolidayKind::Company, Holiday::query()->whereDate('date', '2026-12-31')->sole()->kind);
        $this->assertSame(26, Holiday::query()->whereYear('date', 2026)->count(), '25 dari SKB + 1 libur company; isi ulang tidak menggandakan.');

        // Tahun tanpa data SKB: tidak ada pengisian, layar memberi tahu.
        $this->assertFalse($k->isHoliday(Carbon::parse('2030-01-01')));
        Livewire::actingAs($admin)->test(HolidayCalendar::class)->set('tahun', 2030)->assertSee(__('Data libur nasional tahun ini belum tersedia'), false);

        // Hanya-lihat tidak bisa mengubah.
        Livewire::actingAs($this->makeUser('management'))->test(HolidayCalendar::class)->assertDontSee(__('Tambah libur'))->call('aturAktif', $cuti->id, true)->assertForbidden();
    }
}
