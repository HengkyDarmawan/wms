<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Shipment\Support\ShipmentSearch;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Rekap pengiriman per klien (A-329) — bukan akuntansi dan **tanpa nilai uang**
 * ([D-07](docs/wms/04-keputusan-dan-asumsi.md)): satu baris per item per SJ,
 * berikut hasil bukti terimanya (baik / rusak / kurang).
 *
 * Dua jalur barang ke klien ikut terhitung: SJ yang tujuannya proyek klien, dan
 * SJ ke Gudang Site milik proyek itu — keduanya bermuara di site yang sama.
 * Saringan *Tujuan* memisahkannya bila perlu.
 */
class ClientShipmentRecapReport extends Report
{
    use PeriodFilter;

    private const TUJUAN_PROYEK = 'project_client';

    private const TUJUAN_SITE = 'site_warehouse';

    public function key(): string
    {
        return 'rekap-pengiriman-klien';
    }

    public function title(): string
    {
        return 'Rekap pengiriman per klien';
    }

    public function permission(): string
    {
        return 'shipment.view';
    }

    public function description(): string
    {
        return 'Barang yang sudah dikirim untuk satu klien: per SJ dan per item, berikut jumlah diterima baik, rusak, dan kurang. Tanpa nilai uang.';
    }

    public function columns(): array
    {
        return [
            'tgl_kirim' => 'Tanggal kirim',
            'tgl_terima' => 'Tanggal diterima',
            'sj' => 'No. SJ',
            'proyek' => 'Proyek',
            'po_klien' => 'No. PO klien',
            'gr_klien' => 'No. GR klien',
            'kode_item' => 'Kode item',
            'nama_item' => 'Nama item',
            'dikirim' => 'Dikirim',
            'baik' => 'Diterima baik',
            'rusak' => 'Rusak',
            'kurang' => 'Kurang',
            'satuan' => 'Satuan',
        ];
    }

    public function filters(): array
    {
        return [
            'client_id' => [
                'label' => 'Klien',
                'required' => true,
                'cari' => true,
                'options' => Client::query()->orderBy('name')->get(['id', 'code', 'name'])
                    ->mapWithKeys(fn (Client $c) => [$c->id => $c->code.' — '.$c->name])->all(),
            ],
            'project_id' => ['label' => 'Proyek', 'server' => true],
            'destination_type' => ['label' => 'Tujuan', 'options' => [
                self::TUJUAN_PROYEK => 'Proyek klien',
                self::TUJUAN_SITE => 'Gudang Site',
            ]],
        ] + $this->penyaringPeriode();
    }

    /** Proyek berklien dalam id cakupan pembaca (query lama), dicari ke server (A-396). */
    public function pilihanPenyaring(string $kunci): ?Pilihan
    {
        if ($kunci !== 'project_id') {
            return null;
        }

        $boleh = auth()->user()?->accessibleProjectIds();

        return $this->pilihanProyekDari(Project::query()->whereNotNull('client_id')
            ->when($boleh !== null, fn ($q) => $q->whereIn('id', $boleh))
            ->orderBy('code'));
    }

    public function rows(array $filters): Collection
    {
        $klien = (int) ($filters['client_id'] ?? 0);

        // Klien wajib: tanpa itu laporan ini tidak punya arti, dan menampilkan
        // seluruh pengiriman semua klien justru membocorkan data antarklien.
        if ($klien <= 0) {
            return collect();
        }

        $proyek = $this->proyekKlien($klien, $filters);

        if ($proyek === []) {
            return collect();
        }

        [$dari, $sampai] = $this->periode($filters);
        $tujuan = (string) ($filters['destination_type'] ?? '');
        $siteIds = $this->gudangSite($proyek);

        $baris = ShipmentLine::query()
            ->with([
                'item:id,code,name,base_uom_id',
                'item.baseUom:id,code',
                'shipment:id,number,destination_type,destination_project_id,destination_warehouse_id,shipped_at,delivered_at,created_at',
                'shipment.destinationProject:id,code,name',
                'shipment.destinationWarehouse:id,code,project_id',
                'shipment.proof:id,shipment_id,client_gr_number',
                'proofLines:id,shipment_line_id,qty_good,qty_damaged,qty_missing',
            ])
            ->whereHas('shipment', function ($q) use ($proyek, $siteIds, $tujuan, $dari, $sampai): void {
                // Rekap ini diserahkan ke klien sebagai "barang yang sudah dikirim":
                // SJ yang belum berangkat atau batal tidak ikut dihitung.
                $q->whereIn('status', [ShipmentStatus::Shipped->value, ShipmentStatus::PartiallyDelivered->value, ShipmentStatus::Delivered->value])
                    ->whereBetween('shipped_at', [$dari, $sampai])
                    ->where(function ($w) use ($proyek, $siteIds, $tujuan): void {
                        $ada = false;

                        if ($tujuan !== self::TUJUAN_SITE) {
                            $w->orWhere(fn ($p) => $p->where('destination_type', self::TUJUAN_PROYEK)
                                ->whereIn('destination_project_id', $proyek));
                            $ada = true;
                        }

                        if ($tujuan !== self::TUJUAN_PROYEK && $siteIds !== []) {
                            $w->orWhere(fn ($p) => $p->where('destination_type', self::TUJUAN_SITE)
                                ->whereIn('destination_warehouse_id', $siteIds));
                            $ada = true;
                        }

                        // Kelompok `where` kosong tidak membatasi apa pun, jadi SJ klien
                        // lain akan ikut terbawa. Contohnya: saringan Tujuan = Gudang Site
                        // untuk klien yang proyeknya belum punya Gudang Site.
                        if (! $ada) {
                            $w->whereRaw('1 = 0');
                        }
                    });
            })
            ->orderByDesc('shipment_id')->orderBy('id')
            ->limit(2000)
            ->get();

        $po = ShipmentSearch::clientPoByShipment(
            $baris->pluck('shipment_id')->unique()->map(fn ($id) => (int) $id)->all(),
        );

        $hasil = $baris->map(fn (ShipmentLine $b) => $this->baris($b, $po));

        return $hasil->isEmpty() ? $hasil : $hasil->concat($this->total($hasil));
    }

    /** @param  array<int, string>  $po  @return array<string, mixed> */
    private function baris(ShipmentLine $line, array $po): array
    {
        $sj = $line->shipment;
        $bukti = $line->proofLines;
        $adaBukti = $bukti->isNotEmpty();

        return [
            'tgl_kirim' => $sj?->shipped_at?->lokal()->format('d/m/Y') ?? '—',
            'tgl_terima' => $sj?->delivered_at?->lokal()->format('d/m/Y') ?? '—',
            'sj' => $sj?->number ?? '—',
            'proyek' => $this->labelProyek($sj),
            'po_klien' => $po[$line->shipment_id] ?? '—',
            'gr_klien' => $sj?->proof?->client_gr_number ?? '—',
            'kode_item' => $line->item?->code,
            'nama_item' => $line->item?->name,
            'dikirim' => (float) $line->qty_shipped,
            'baik' => $adaBukti ? (float) $bukti->sum('qty_good') : '—',
            'rusak' => $adaBukti ? (float) $bukti->sum('qty_damaged') : '—',
            'kurang' => $adaBukti ? (float) $bukti->sum('qty_missing') : '—',
            'satuan' => $line->item?->baseUom?->code,
        ];
    }

    /**
     * Baris TOTAL per item di akhir tabel, mengikuti pola laporan Kinerja
     * pengiriman. Kolom bukti terima hanya menjumlahkan yang sudah ada buktinya.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return Collection<int, array<string, mixed>>
     */
    private function total(Collection $baris): Collection
    {
        return $baris->groupBy('kode_item')->map(function (Collection $grup): array {
            $angka = fn (string $kunci) => $grup->filter(fn (array $b) => is_numeric($b[$kunci]))->sum($kunci);

            return [
                'tgl_kirim' => 'TOTAL',
                'tgl_terima' => '',
                'sj' => '',
                'proyek' => '',
                'po_klien' => '',
                'gr_klien' => '',
                'kode_item' => $grup->first()['kode_item'],
                'nama_item' => $grup->first()['nama_item'],
                'dikirim' => $angka('dikirim'),
                'baik' => $angka('baik'),
                'rusak' => $angka('rusak'),
                'kurang' => $angka('kurang'),
                'satuan' => $grup->first()['satuan'],
            ];
        })->values();
    }

    private function labelProyek(?Shipment $sj): string
    {
        return $sj?->destinationProject?->code
            ?? $sj?->destinationWarehouse?->code
            ?? '—';
    }

    /**
     * Proyek klien yang dipakai, dipersempit bila penyaring proyek diisi.
     *
     * @return array<int, int>
     */
    private function proyekKlien(int $klien, array $filters): array
    {
        $ids = Project::query()->where('client_id', $klien)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        // BR-ACC-05: pembaca bercakupan proyek hanya melihat proyeknya sendiri.
        $boleh = auth()->user()?->accessibleProjectIds();

        if ($boleh !== null) {
            $ids = array_values(array_intersect($ids, array_map('intval', $boleh)));
        }

        $pilih = (int) ($filters['project_id'] ?? 0);

        if ($pilih > 0) {
            return in_array($pilih, $ids, true) ? [$pilih] : [];
        }

        return $ids;
    }

    /** @param  array<int, int>  $proyek  @return array<int, int> */
    private function gudangSite(array $proyek): array
    {
        return Warehouse::withoutGlobalScopes()->whereIn('project_id', $proyek)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
