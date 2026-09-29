<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Actions\ApplyLayoutChanges;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\FloorPlanObjectType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Denah gudang 2D — **Denah ringan** (K-H, A-353).
 *
 * Halaman digambar sekali: data denah (zona → rak → tingkat → petak, objek
 * denah, gedung) dikirim sebagai JSON dan digambar di browser oleh Alpine
 * `denahGedung`. Setelah itu tidak ada render ulang Livewire:
 *  - klik rak → {@see isiRak()} mengembalikan isi rak saja;
 *  - cari, ganti warna, zoom → di browser;
 *  - mode Atur → semua perubahan di browser (bisa diurungkan), lalu satu
 *    {@see simpanPerubahan()} yang menerapkannya dalam satu transaksi.
 *
 * Sejak A-254/A-255/A-271/A-320–A-325: warna status/umur, tertua (FIFO),
 * tambah zona/rak/tingkat/bin/area lantai/objek, ukuran gedung, putar,
 * tumpukan diperingatkan, nonaktif tanpa hapus. `ringkas` = sematan
 * hanya-lihat di Daftar Gudang (A-323). Tanpa pustaka tambahan (D-05).
 */
class WarehouseLayout extends Component
{
    /** Denah bergambar sampai sekian bin; lebih dari itu tampil sebagai daftar (A-353). */
    public const MAKS_BIN_GAMBAR = 2000;

    #[Locked]
    public int $warehouseId;

    /** A-323: sematan hanya-lihat (Daftar Gudang mode Denah). */
    #[Locked]
    public bool $ringkas = false;

    public function mount(Warehouse $warehouse, bool $ringkas = false): void
    {
        $this->authorize('view', $warehouse);
        $this->warehouseId = (int) $warehouse->id;
        $this->ringkas = $ringkas;
    }

    /** K-H: isi satu rak saat diklik — tanpa menggambar ulang halaman. */
    #[Renderless]
    public function isiRak(int $id, WarehouseLayoutData $data): array
    {
        $gudang = $this->gudang();
        $this->authorize('view', $gudang);

        return $data->rakDetail($gudang, $id);
    }

    /**
     * K-H: semua perubahan mode Atur dalam satu kiriman & satu transaksi.
     *
     * @param  array<int, array<string, mixed>>  $ops
     * @return array{ok: bool, galat?: array<int, array<string, mixed>>, denah?: array<string, mixed>, pesan: string}
     */
    #[Renderless]
    public function simpanPerubahan(array $ops, ApplyLayoutChanges $action, WarehouseLayoutData $data): array
    {
        abort_if($this->ringkas, 403);
        $gudang = $this->gudang();
        $this->authorize('manageLayout', $gudang);

        try {
            $hasil = $action->handle($gudang, $ops, auth()->user());
        } catch (WarehouseRuleException $e) {
            return ['ok' => false, 'galat' => [], 'pesan' => $e->getMessage()];
        }

        if (! $hasil['ok']) {
            return ['ok' => false, 'galat' => $hasil['galat'], 'pesan' => __(':n perubahan ditolak — tidak ada yang disimpan. Perbaiki yang ditandai merah atau urungkan.', ['n' => count($hasil['galat'])])];
        }

        return ['ok' => true, 'denah' => $data->payload($gudang->refresh()), 'pesan' => __(':n perubahan disimpan.', ['n' => $hasil['jumlah']])];
    }

    /** Muat ulang data denah (mis. setelah orang lain mengubahnya). */
    #[Renderless]
    public function muatUlang(WarehouseLayoutData $data): array
    {
        $gudang = $this->gudang();
        $this->authorize('view', $gudang);

        return $data->payload($gudang);
    }

    public function render(WarehouseLayoutData $data): View
    {
        $gudang = $this->gudang();
        $bolehUbah = ! $this->ringkas && (auth()->user()?->can('manageLayout', $gudang) ?? false);

        return view('livewire.warehouse.warehouse-layout', [
            'gudang' => $gudang,
            'awal' => [
                'denah' => $data->payload($gudang),
                'meta' => [
                    'skala' => WarehouseLayoutData::SKALA,
                    'gudang' => ['id' => $gudang->id, 'code' => $gudang->code, 'name' => $gudang->name],
                    'bolehUbah' => $bolehUbah,
                    'ringkas' => $this->ringkas,
                    'maksBinGambar' => self::MAKS_BIN_GAMBAR,
                    'maksLevel' => SaveWarehouseLayout::MAKS_LEVEL,
                    'maksBinPerLevel' => SaveWarehouseLayout::MAKS_BIN_PER_LEVEL,
                    'jenisObjek' => collect(FloorPlanObjectType::cases())->mapWithKeys(function (FloorPlanObjectType $t) {
                        [$p, $l] = $t->defaultSize();
                        [$isi, $garis] = $t->colors();

                        return [$t->value => ['label' => $t->label(), 'p' => $p, 'l' => $l, 'fill' => $isi, 'stroke' => $garis, 'solid' => $t->solid()]];
                    })->all(),
                    'teks' => ['gedung' => __('Gedung'), 'ukuranOtomatis' => __('ukuran otomatis'), 'area' => __('Area lantai')],
                ],
            ],
            'bolehUbah' => $bolehUbah,
            'jenisObjek' => FloorPlanObjectType::options(),
            'alasan' => $bolehUbah ? ReasonCode::query()->where('context', ReasonContext::Cancel->value)->where('is_active', true)->orderBy('label')->pluck('label', 'code')->all() : [],
        ]);
    }

    private function gudang(): Warehouse
    {
        return Warehouse::query()->findOrFail($this->warehouseId);
    }
}
