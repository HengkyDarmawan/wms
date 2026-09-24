<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports;

use App\Domain\Access\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Daftar seluruh laporan aplikasi.
 *
 * Modul menambahkan laporannya di sini, bukan membuat layar sendiri-sendiri,
 * supaya penyaring, tabel, dan ekspor berperilaku sama di semua modul.
 */
class ReportRegistry
{
    /** @var array<int, class-string<Report>> */
    private const REPORTS = [
        Definitions\UserRoleScopeReport::class,
        Definitions\LoginLogReport::class,
        Definitions\ItemListReport::class,
        Definitions\ProvisionalItemReport::class,
        Definitions\ProjectListReport::class,
        Definitions\WarehouseListReport::class,
        Definitions\BinListReport::class,
        Definitions\ProjectMaterialReport::class,
    ];

    /** @return Collection<int, Report> */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn (string $kelas) => app($kelas));
    }

    /**
     * Laporan yang boleh dibuka user ini.
     *
     * @return Collection<int, Report>
     */
    public function availableTo(User $user): Collection
    {
        return $this->all()->filter(fn (Report $r) => $user->hasPermission($r->permission()))->values();
    }

    public function find(string $key): Report
    {
        $laporan = $this->all()->first(fn (Report $r) => $r->key() === $key);

        if ($laporan === null) {
            throw new InvalidArgumentException('Laporan "'.$key.'" tidak dikenal.');
        }

        return $laporan;
    }
}
