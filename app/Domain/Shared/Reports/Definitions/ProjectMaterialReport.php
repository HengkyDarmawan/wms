<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Issue\Support\ProjectMaterialSummary;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Reports\Report;
use Illuminate\Support\Collection;

/**
 * Laporan Blueprint §9 *Material per proyek* (23-pemakaian §9, A-151):
 * diminta vs terkirim vs **terpakai** vs diretur vs posisi on-site, per proyek
 * × item, satuan dasar, tanpa nilai uang (D-07). Proyek dibatasi cakupan.
 */
class ProjectMaterialReport extends Report
{
    public function key(): string
    {
        return 'material-per-proyek';
    }

    public function title(): string
    {
        return 'Material per proyek';
    }

    public function permission(): string
    {
        return 'issue.view';
    }

    public function description(): string
    {
        return 'Per proyek dan item: jumlah diminta, terkirim, terpakai (ISU), diretur, sisa di Gudang Site, dan aset di proyek — dibaca dari kartu stok.';
    }

    public function columns(): array
    {
        return [
            'proyek' => 'Proyek',
            'kode_item' => 'Kode item',
            'nama_item' => 'Nama item',
            'satuan' => 'Satuan',
            'diminta' => 'Diminta',
            'terkirim' => 'Terkirim',
            'terpakai' => 'Terpakai',
            'diretur' => 'Diretur',
            'di_site' => 'Di Gudang Site',
            'aset_proyek' => 'Aset di proyek',
        ];
    }

    public function filters(): array
    {
        return [
            'project_id' => ['label' => 'Proyek', 'options' => $this->proyek()->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->name])->all()],
            'item' => ['label' => 'Item (kode/nama mengandung)'],
        ];
    }

    public function rows(array $filters): Collection
    {
        $boleh = $this->proyek()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $pilih = (int) ($filters['project_id'] ?? 0);
        $ids = $pilih > 0 ? array_values(array_intersect($boleh, [$pilih])) : $boleh;

        return app(ProjectMaterialSummary::class)
            ->rows($ids, (string) ($filters['item'] ?? ''))
            ->map(fn (array $r) => [
                'proyek' => $r['project_code'],
                'kode_item' => $r['item_code'],
                'nama_item' => $r['item_name'],
                'satuan' => $r['uom'],
                'diminta' => $r['diminta'],
                'terkirim' => $r['terkirim'],
                'terpakai' => $r['terpakai'],
                'diretur' => $r['diretur'],
                'di_site' => $r['di_site'],
                'aset_proyek' => $r['aset_proyek'],
            ]);
    }

    /** @return Collection<int, Project> proyek dalam cakupan pengguna (BR-ACC-05) */
    private function proyek(): Collection
    {
        $ids = auth()->user()?->accessibleProjectIds();

        return Project::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }
}
