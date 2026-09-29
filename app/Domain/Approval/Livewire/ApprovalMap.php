<?php

declare(strict_types=1);

namespace App\Domain\Approval\Livewire;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\SimulateApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Livewire\Concerns\HandlesApprovalRules;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Approval\Support\ApprovalRuleSentence;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 20-approval §6.6 — **Peta approval** (A-349): siapa menyetujui apa
 * dalam kalimat, bagan jabatan (jabatan → atasan jabatan → pemegang), dan
 * "Cek untuk orang" yang memakai simulasi yang sama dengan pengajuan
 * sungguhan (BR-APR-11). Hanya membaca.
 */
class ApprovalMap extends Component
{
    use HandlesApprovalRules;

    public string $pemohon = '';

    public string $jenis = '';

    public string $gudang = '';

    public string $proyek = '';

    /** @var array<string, mixed>|null */
    public ?array $hasil = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalRule::class);
        $this->jenis = array_key_first(app(ApprovalRegistry::class)->typeOptions()) ?? '';
    }

    public function cek(SimulateApproval $action): void
    {
        $this->authorize('viewAny', ApprovalRule::class);
        $this->hasil = null;

        $this->validate([
            'pemohon' => ['required', 'integer', 'exists:users,id'],
            'jenis' => ['required', 'string'],
        ], [], ['pemohon' => __('Pemohon'), 'jenis' => __('Jenis dokumen')]);

        $jenis = ApprovalDocumentType::tryFrom($this->jenis);

        if ($jenis === null || ! app(ApprovalRegistry::class)->has($jenis)) {
            $this->addError('jenis', __('Jenis dokumen belum tersambung ke approval.'));

            return;
        }

        $this->jalankan(function () use ($action, $jenis): void {
            $ctx = $action->contextForPerson(
                $jenis,
                (int) $this->pemohon,
                $this->gudang !== '' ? (int) $this->gudang : null,
                $this->proyek !== '' ? (int) $this->proyek : null,
            );

            $this->hasil = $action->run($ctx);
        }, 'cek');
    }

    public function render(): View
    {
        $registry = app(ApprovalRegistry::class);
        $kalimat = app(ApprovalRuleSentence::class);

        $aturan = ApprovalRule::query()->active()->with('steps')->evaluationOrder()->get()
            ->groupBy(fn (ApprovalRule $r) => $r->document_type->value);

        return view('livewire.approval.map', [
            'baris' => collect($registry->types())->map(fn (ApprovalDocumentType $t) => [
                'jenis' => $t,
                'aturan' => ($aturan[$t->value] ?? collect())->map(fn (ApprovalRule $r) => [
                    'id' => $r->id,
                    'nama' => $r->name,
                    'dasar' => $r->is_basic,
                    'kalimat' => $kalimat->aturan($r),
                ])->all(),
                'tanpa' => $kalimat->tanpaAturan($t),
            ]),
            'bagan' => $this->bagan(),
            'types' => $registry->typeOptions(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name', 'client_id']),
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Pohon jabatan dari peta atasan (A-344), diratakan dengan kedalaman.
     *
     * @return Collection<int, array{jabatan: Position, unit: ?OrgUnit, depth: int, pemegang: array<int, string>}>
     */
    private function bagan(): Collection
    {
        $jabatan = Position::query()->where('is_active', true)->with('orgUnit:id,name')->orderBy('level')->orderBy('name')->get();
        $pemegang = User::query()->active()->internal()->whereNotNull('position_id')->orderBy('name')
            ->get(['id', 'name', 'position_id'])->groupBy('position_id');

        $aktif = $jabatan->pluck('id')->all();
        $hasil = collect();

        $susun = function (?int $induk, int $depth) use (&$susun, $jabatan, $pemegang, $aktif, &$hasil): void {
            $anak = $jabatan->filter(fn (Position $p) => $induk === null
                ? ($p->reports_to_position_id === null || ! in_array($p->reports_to_position_id, $aktif, true))
                : $p->reports_to_position_id === $induk);

            foreach ($anak as $p) {
                if ($hasil->contains(fn ($b) => $b['jabatan']->id === $p->id) || $depth > 20) {
                    continue;
                }

                $hasil->push([
                    'jabatan' => $p,
                    'unit' => $p->orgUnit,
                    'depth' => $depth,
                    'pemegang' => ($pemegang[$p->id] ?? collect())->pluck('name')->all(),
                ]);

                $susun($p->id, $depth + 1);
            }
        };

        $susun(null, 0);

        return $hasil;
    }
}
