<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Wizard setup awal company (Blueprint §4.2, alur 10 langkah 4, glosarium
 * `setup_wizard`, A-191): daftar langkah yang dihitung dari data company,
 * persetujuan ketentuan layanan & kebijakan privasi (NFR-11, teks final
 * menunggu O-11), dan tanda setup selesai. Tidak membuat data sendiri —
 * setiap langkah menaut ke layar modulnya (atau impor Excel).
 */
class SetupWizard
{
    public const TERMS_KEY = 'terms_accepted';

    public const DONE_KEY = 'setup_completed_at';

    /** Versi naskah ketentuan yang disetujui; naik saat teks final O-11 terbit. */
    public const TERMS_VERSION = 'sementara-2026-09';

    /** @return array<int, array{key: string, label: string, hint: string, done: bool, url: ?string, optional: bool}> */
    public function steps(): array
    {
        $bins = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::Storage->value);

        return [
            $this->langkah('terms', 'Setujui ketentuan layanan & kebijakan privasi', 'Wajib sebelum memakai data pribadi (UU PDP).', CompanySetting::get(self::TERMS_KEY) !== null, null),
            $this->langkah('warehouse', 'Buat gudang', 'Gudang utama/cabang; Gudang Site dibuat per proyek.', Warehouse::query()->withoutGlobalScopes()->exists(), $this->rute('warehouses.index')),
            $this->langkah('bins', 'Susun lokasi rak & bin', 'Minimal satu bin penyimpanan per gudang.', $bins->exists(), $this->rute('bins.index')),
            $this->langkah('items', 'Daftarkan item', 'Satu per satu atau impor Excel.', Item::query()->exists(), $this->rute('imports.index') ?? $this->rute('items.index')),
            $this->langkah('projects', 'Daftarkan klien & proyek', 'Proyek menentukan tujuan permintaan dan Gudang Site.', Project::query()->where('is_internal', false)->exists(), $this->rute('projects.index')),
            $this->langkah('users', 'Undang pengguna & atur role', 'Kepala gudang, staf, pemohon, klien.', User::query()->count() > 1, $this->rute('users.index')),
            $this->langkah('approval', 'Atur aturan approval', 'Opsional: tanpa aturan dokumen disetujui otomatis.', ApprovalRule::query()->exists(), $this->rute('approval.rules.index'), true),
            $this->langkah('opening', 'Masukkan saldo awal', 'Impor Excel, penyesuaian stok, atau opname pembukaan.', StockBalance::query()->where('qty_base', '>', 0)->exists(), $this->rute('imports.index') ?? $this->rute('adjustments.create'), true),
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

    /** @return array{key: string, label: string, hint: string, done: bool, url: ?string, optional: bool} */
    private function langkah(string $key, string $label, string $hint, bool $done, ?string $url, bool $optional = false): array
    {
        return compact('key', 'label', 'hint', 'done', 'url', 'optional');
    }

    private function rute(string $nama): ?string
    {
        return app('router')->has($nama) ? route($nama) : null;
    }
}
