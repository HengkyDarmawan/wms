<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;

/**
 * Wizard setup awal company (Blueprint §4.2, alur 10 langkah 4, glosarium
 * `setup_wizard`, A-191): daftar langkah yang dihitung dari data company,
 * persetujuan ketentuan layanan & kebijakan privasi (NFR-11, teks final
 * menunggu O-11), dan tanda setup selesai. Tidak membuat data sendiri —
 * setiap langkah menaut ke layar modulnya (atau impor Excel).
 *
 * A-402: tiap langkah membawa kalimat awam *Dianggap selesai bila…*
 * (`selesai_bila`) dan `catatan` keadaan saat ini (mis. "3 dari 12 barang
 * sudah punya tempat", "1 penyesuaian saldo awal menunggu approval").
 */
class SetupWizard
{
    public const TERMS_KEY = 'terms_accepted';

    public const DONE_KEY = 'setup_completed_at';

    /** Versi naskah ketentuan yang disetujui; naik saat teks final O-11 terbit. */
    public const TERMS_VERSION = 'sementara-2026-09';

    /** Kode alasan penyesuaian untuk saldo awal (`MasterReferenceSeeder`, A-207). */
    public const ALASAN_SALDO_AWAL = 'OPENING';

    /** @return array<int, array{key: string, label: string, hint: string, done: bool, url: ?string, optional: bool, links: array<int, array{label: string, url: string}>, selesai_bila: string, catatan: ?array{teks: string, url: ?string}}> */
    public function steps(): array
    {
        $bins = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::Storage->value);

        // K-L (A-380): gudang → rak → item → tata letak barang (opsional) → saldo awal;
        // proyek, pengguna, dan approval sesudahnya.
        $gudang = Warehouse::query()->withoutGlobalScopes()->where('is_active', true)
            ->whereHas('type', fn ($q) => $q->where('code', '!=', WarehouseType::SITE))->orderBy('id')->first();
        $denahUrl = $gudang !== null && app('router')->has('warehouses.layout') ? route('warehouses.layout', ['warehouse' => $gudang->id]) : null;
        $tataUrl = $denahUrl !== null ? $denahUrl.'?mode=tata' : null;
        $impor = $this->rute('imports.index');

        $itemAktif = Item::query()->where('status', ItemStatus::Active->value)->count();
        $itemBertempat = ItemStorageLocation::query()->withoutGlobalScopes()->distinct()->count('item_id');

        // A-407: akun dasar = nonaktif, belum berpassword, email `<role>@<kode>.wms`.
        $adaAkunDasar = User::query()->where('is_active', false)->whereNull('password')->where('email', 'like', '%.wms')->exists();

        return [
            $this->langkah('terms', 'Setujui ketentuan layanan & kebijakan privasi', 'Wajib sebelum memakai data pribadi (UU PDP).', CompanySetting::get(self::TERMS_KEY) !== null, null,
                selesaiBila: 'kotak persetujuan di bawah dicentang dan disimpan.'),
            $this->langkah('warehouse', 'Buat gudang', 'Gudang utama/cabang; Gudang Site dibuat per proyek.', Warehouse::query()->withoutGlobalScopes()->exists(), $this->rute('warehouses.index'),
                selesaiBila: 'ada minimal satu gudang.'),
            $this->langkah('bins', 'Susun lokasi rak & bin', 'Buka Denah gudang → Atur denah → Tambah zona, lalu Tambah rak (isi tingkat & bin per tingkat) → Simpan perubahan.',
                $bins->exists(), $denahUrl ?? $this->rute('warehouses.index'), false, array_values(array_filter([
                    $gudang !== null && app('router')->has('warehouses.show') ? ['label' => 'Zona & rak di detail gudang', 'url' => route('warehouses.show', $gudang)] : null,
                    $impor !== null ? ['label' => 'Impor struktur gudang dari Excel', 'url' => $impor.'#impor-bins'] : null,
                ])), selesaiBila: 'ada minimal satu bin penyimpanan di gudang.'),
            $this->langkah('items', 'Daftarkan item', 'Satu per satu atau impor Excel.', Item::query()->exists(), $impor ?? $this->rute('items.index'),
                selesaiBila: 'ada minimal satu item.'),
            $this->langkah('layout', 'Atur tata letak barang', 'Opsional: tetapkan di mana tiap barang disimpan. Buka barang → kartu Tempat simpan → Ubah → Pilih di denah (klik rak di gambar, ketuk petak bin). Dipakai untuk saran bin saat barang masuk dan saldo awal.',
                $itemBertempat > 0, $this->rute('items.index'), true, array_values(array_filter([
                    $tataUrl !== null ? ['label' => 'Banyak barang sekaligus: mode Tata letak di Denah', 'url' => $tataUrl] : null,
                    $impor !== null ? ['label' => 'Impor tempat simpan dari Excel', 'url' => $impor.'#impor-storage-locations'] : null,
                ])), selesaiBila: 'minimal satu barang punya tempat simpan.',
                catatan: $itemAktif > 0 ? ['teks' => __(':n dari :m barang aktif sudah punya tempat simpan.', ['n' => $itemBertempat, 'm' => $itemAktif]), 'url' => null] : null),
            $this->langkah('opening', 'Masukkan saldo awal', 'Sedikit barang: isi lewat Penyesuaian stok dengan alasan Saldo awal (tombol Kerjakan). Banyak barang: impor Excel. Stok baru tercatat setelah penyesuaian disetujui.',
                StockBalance::query()->where('qty_base', '>', 0)->exists(), $this->urlSaldoAwal(), true, array_values(array_filter([
                    $impor !== null ? ['label' => 'Impor Excel saldo awal', 'url' => $impor.'#impor-opening-stock'] : null,
                    ($adj = $this->rute('adjustments.index')) !== null ? ['label' => 'Daftar penyesuaian', 'url' => $adj] : null,
                ])), selesaiBila: 'penyesuaian Saldo awal sudah disetujui Kepala Gudang dan stok tercatat.',
                catatan: $this->catatanSaldoAwal()),
            $this->langkah('projects', 'Daftarkan klien & proyek', 'Proyek menentukan tujuan permintaan dan Gudang Site.', Project::query()->where('is_internal', false)->exists(), $this->rute('projects.index'),
                selesaiBila: 'ada minimal satu proyek klien (bukan internal).'),
            // A-407: akun dasar dibuat nonaktif, jadi hanya pengguna aktif yang dihitung.
            $this->langkah('users', 'Undang pengguna & atur role', $adaAkunDasar
                    ? 'Akun dasar per role (Manajemen, Kepala Gudang, Staf Gudang, Pemohon Internal, dll.) sudah dibuat tetapi nonaktif. Buka akunnya, ganti nama & email ke orang sebenarnya, aktifkan, lalu buatkan password atau kirim undangan. Klien ditambahkan sendiri.'
                    : 'Kepala gudang, staf, pemohon, klien.',
                User::query()->where('is_active', true)->count() > 1, $this->rute('users.index'),
                selesaiBila: 'ada pengguna aktif lain selain Admin Company.'),
            // A-405: company baru sudah memegang aturan dasar aktif sejak provisioning.
            $this->langkah('approval', 'Atur aturan approval', ApprovalRule::query()->where('is_basic', true)->exists()
                    ? 'Opsional: aturan dasar sudah aktif — Permintaan disetujui Kepala gudang terkait, dokumen lain oleh atasan langsung pemohon. Ubah bila perlu; tombol Kembalikan ke aturan dasar memulihkannya.'
                    : 'Opsional: tanpa aturan dokumen disetujui otomatis. Tombol Pasang aturan dasar membuat aturan umum sekali klik.',
                ApprovalRule::query()->exists(), $this->rute('approval.rules.index'), true,
                selesaiBila: 'ada minimal satu aturan approval.'),
        ];
    }

    /** @return array{done: int, total: int, required_left: int} */
    public function progress(): array
    {
        $langkah = collect($this->steps());

        return [
            'done' => $langkah->where('done', true)->count(),
            'total' => $langkah->count(),
            'required_left' => $langkah->where('optional', false)->where('done', false)->count(),
        ];
    }

    public function isCompleted(): bool
    {
        return CompanySetting::get(self::DONE_KEY) !== null;
    }

    public function acceptTerms(User $actor): void
    {
        CompanySetting::put(self::TERMS_KEY, ['version' => self::TERMS_VERSION, 'by' => $actor->id, 'name' => $actor->name, 'at' => now()->toIso8601String()]);
    }

    public function complete(User $actor): void
    {
        CompanySetting::put(self::DONE_KEY, ['by' => $actor->id, 'at' => now()->toIso8601String()]);
    }

    /** A-402: form Penyesuaian dengan alasan Saldo awal sudah terpilih (`?reason=OPENING`). */
    private function urlSaldoAwal(): ?string
    {
        if (app('router')->has('adjustments.create')) {
            return route('adjustments.create', ['reason' => self::ALASAN_SALDO_AWAL]);
        }

        return $this->rute('imports.index');
    }

    /**
     * A-402: penyesuaian Saldo awal yang belum disetujui — supaya jelas kenapa
     * langkah belum tercentang. Status dari katalog 06 §2.12 (tanpa status baru).
     *
     * @return ?array{teks: string, url: ?string}
     */
    private function catatanSaldoAwal(): ?array
    {
        $menunggu = StockAdjustment::query()->withoutGlobalScopes()
            ->whereIn('status', [StockAdjustmentStatus::Submitted->value, StockAdjustmentStatus::PendingApproval->value])
            ->whereHas('reason', fn ($q) => $q->where('code', self::ALASAN_SALDO_AWAL))
            ->orderBy('id')->get(['id', 'number']);

        if ($menunggu->isEmpty()) {
            return null;
        }

        $pertama = $menunggu->first();

        return [
            'teks' => __(':n penyesuaian saldo awal menunggu approval Kepala Gudang (:nomor). Langkah ini tercentang setelah disetujui.', ['n' => $menunggu->count(), 'nomor' => $pertama->number]),
            'url' => app('router')->has('adjustments.show') ? route('adjustments.show', $pertama->id) : null,
        ];
    }

    /**
     * @param  array<int, array{label: string, url: string}>  $links  tautan tambahan (mis. Denah & impor)
     * @param  ?array{teks: string, url: ?string}  $catatan  keadaan saat ini (A-402)
     * @return array{key: string, label: string, hint: string, done: bool, url: ?string, optional: bool, links: array<int, array{label: string, url: string}>, selesai_bila: string, catatan: ?array{teks: string, url: ?string}}
     */
    private function langkah(string $key, string $label, string $hint, bool $done, ?string $url, bool $optional = false, array $links = [], string $selesaiBila = '', ?array $catatan = null): array
    {
        return compact('key', 'label', 'hint', 'done', 'url', 'optional', 'links') + ['selesai_bila' => $selesaiBila, 'catatan' => $catatan];
    }

    private function rute(string $nama): ?string
    {
        return app('router')->has($nama) ? route($nama) : null;
    }
}
