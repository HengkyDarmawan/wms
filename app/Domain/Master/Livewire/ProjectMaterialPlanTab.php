<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Issue\Support\ProjectMaterialSummary;
use App\Domain\Master\Actions\ImportProjectMaterialPlan;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ProjectMaterialPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tab **Rencana material** di hub proyek (34-rencana-material §6.1, A-428):
 * rencana versi berlaku vs diminta/terkirim/terpakai, unggah versi baru dari
 * Excel, unduh templat berisi rencana berlaku, riwayat versi. Komponen sendiri
 * supaya ProjectDetail tidak membengkak.
 */
class ProjectMaterialPlanTab extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $projectId;

    /** @var TemporaryUploadedFile|null */
    public $berkas = null;

    public string $galatBaris = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewPlan', $project);
        $this->projectId = (int) $project->id;
    }

    public function render(): View
    {
        $baris = app(ProjectMaterialSummary::class)->rows([$this->projectId])
            ->filter(fn (array $r) => $r['rencana'] !== null || $r['diminta'] > 0)
            ->map(fn (array $r) => $r + [
                'sisa' => $r['rencana'] === null ? null : round($r['rencana'] - $r['diminta'], 4),
                'melebihi' => $r['rencana'] !== null && $r['diminta'] - $r['rencana'] > 0.00005,
            ])
            ->sortBy([['melebihi', 'desc'], ['item_code', 'asc']])->values();

        return view('livewire.master.project-material-plan', [
            'baris' => $baris,
            'berlaku' => ProjectMaterialPlan::query()->current()->where('project_id', $this->projectId)->with('creator:id,name')->first(),
            'versi' => ProjectMaterialPlan::query()->where('project_id', $this->projectId)
                ->groupBy('version')->orderByDesc('version')
                ->selectRaw('version, count(*) as jumlah, max(created_at) as waktu, max(created_by) as oleh, max(source_file) as berkas, max(is_current) as berlaku')
                ->get(),
            'nama' => DB::table('users')->whereIn('id', ProjectMaterialPlan::query()->where('project_id', $this->projectId)->distinct()->pluck('created_by')->filter())
                ->pluck('name', 'id'),
            'bisaUnggah' => auth()->user()->hasPermission('project.plan') && auth()->user()->canAccessProject($this->projectId),
        ]);
    }

    public function unggah(ImportProjectMaterialPlan $action): void
    {
        $this->galatBaris = '';
        $this->validate(['berkas' => ['required', 'file', 'max:5120']], [], ['berkas' => __('Berkas Excel')]);

        try {
            $n = $action->handle(Project::query()->findOrFail($this->projectId), $this->berkas, auth()->user());
        } catch (MasterRuleException $e) {
            $this->addError('berkas', $e->getMessage());
            $this->galatBaris = (string) ($e->fieldErrors['rows'] ?? '');

            return;
        }

        $this->reset('berkas');
        $this->dispatch('pesan', teks: __('Rencana material versi baru disimpan: :n item.', ['n' => $n]));
    }

    public function unduhTemplat(): StreamedResponse
    {
        $proyek = Project::query()->findOrFail($this->projectId);
        $this->authorize('viewPlan', $proyek);

        $isi = ProjectMaterialPlan::query()->current()->where('project_id', $this->projectId)->with('item:id,code')->get()
            ->sortBy(fn ($p) => $p->item?->code)
            ->map(fn ($p) => [$p->item?->code, (float) $p->planned_qty_base])->values()->all();

        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('Rencana');
        $lembar->fromArray([array_values(ImportProjectMaterialPlan::COLUMNS), ...($isi ?: [['BAUT-M12', 500]])]);
        $lembar->getStyle('A1:B1')->getFont()->setBold(true);
        $lembar->getColumnDimension('A')->setAutoSize(true);
        $lembar->getColumnDimension('B')->setAutoSize(true);

        return response()->streamDownload(fn () => (new Xlsx($buku))->save('php://output'), 'rencana-material-'.$proyek->code.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
