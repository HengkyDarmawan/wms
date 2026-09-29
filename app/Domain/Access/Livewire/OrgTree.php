<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\DeactivateOrgUnit;
use App\Domain\Access\Actions\SaveOrgUnit;
use App\Domain\Access\Actions\SavePosition;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Atasan;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 10-access §6.5 — pohon unit organisasi, jabatan per unit, dan daftar
 * user beserta atasan langsungnya (D-16; dipakai aturan approval Blueprint §8.1).
 */
class OrgTree extends Component
{
    public ?int $selectedId = null;

    // --- form unit
    public ?int $editingUnitId = null;

    public bool $formUnitTampil = false;

    public string $unitCode = '';

    public string $unitName = '';

    public ?int $unitParentId = null;

    // --- form jabatan
    public ?int $editingPositionId = null;

    public bool $formPositionTampil = false;

    public string $positionCode = '';

    public string $positionName = '';

    /** Jabatan atasan (peta jabatan, A-344); kosong = puncak. Level dihitung otomatis. */
    public ?int $positionReportsTo = null;

    public function mount(): void
    {
        $this->authorize('viewAny', OrgUnit::class);

        $this->selectedId = OrgUnit::query()->orderBy('id')->value('id');
    }

    public function pilihUnit(int $unitId): void
    {
        $this->selectedId = $unitId;
        $this->tutupForm();
    }

    public function tutupForm(): void
    {
        $this->formUnitTampil = false;
        $this->formPositionTampil = false;
        $this->editingUnitId = null;
        $this->editingPositionId = null;
        $this->resetValidation();
    }

    // ------------------------------------------------------------------ unit

    public function unitBaru(?int $parentId = null): void
    {
        $this->authorize('create', OrgUnit::class);

        $this->tutupForm();
        $this->formUnitTampil = true;
        $this->unitCode = '';
        $this->unitName = '';
        $this->unitParentId = $parentId ?? $this->selectedId;
    }

    public function editUnit(int $unitId): void
    {
        $unit = OrgUnit::findOrFail($unitId);

        $this->authorize('update', $unit);

        $this->tutupForm();
        $this->formUnitTampil = true;
        $this->editingUnitId = $unit->id;
        $this->unitCode = $unit->code;
        $this->unitName = $unit->name;
        $this->unitParentId = $unit->parent_id;
    }

    public function simpanUnit(SaveOrgUnit $action): void
    {
        $unit = $this->editingUnitId !== null ? OrgUnit::findOrFail($this->editingUnitId) : null;

        $this->authorize($unit === null ? 'create' : 'update', $unit ?? OrgUnit::class);

        $this->validate([
            'unitCode' => $this->editingUnitId === null ? ['required', 'string', 'max:30'] : ['nullable'],
            'unitName' => ['required', 'string', 'max:100'],
            'unitParentId' => ['nullable', 'integer', 'exists:org_units,id'],
        ], [], [
            'unitCode' => 'Kode unit',
            'unitName' => 'Nama unit',
            'unitParentId' => 'Induk',
        ]);

        try {
            $hasil = $action->handle($unit, [
                'code' => $this->unitCode,
                'name' => $this->unitName,
                'parent_id' => $this->unitParentId,
            ], auth()->user());
        } catch (AccessRuleException $e) {
            $this->addError('unitName', $e->getMessage());

            return;
        }

        $this->selectedId = $hasil->id;
        $this->tutupForm();
        $this->dispatch('pesan', teks: 'Unit '.$hasil->name.' disimpan.');
    }

    public function nonaktifkanUnit(int $unitId, DeactivateOrgUnit $action): void
    {
        $unit = OrgUnit::findOrFail($unitId);

        $this->authorize('update', $unit);

        try {
            $action->handle($unit, auth()->user());
        } catch (AccessRuleException $e) {
            $this->dispatch('pesan', teks: $e->getMessage(), jenis: 'danger');

            return;
        }

        $this->dispatch('pesan', teks: 'Unit '.$unit->name.' dinonaktifkan.');
    }

    public function aktifkanUnit(int $unitId, DeactivateOrgUnit $action): void
    {
        $unit = OrgUnit::findOrFail($unitId);

        $this->authorize('update', $unit);

        $action->reactivate($unit, auth()->user());

        $this->dispatch('pesan', teks: 'Unit '.$unit->name.' diaktifkan kembali.');
    }

    // ----------------------------------------------------------------- jabatan

    public function jabatanBaru(): void
    {
        $this->authorize('create', OrgUnit::class);

        $this->tutupForm();
        $this->formPositionTampil = true;
        $this->positionCode = '';
        $this->positionName = '';
        $this->positionReportsTo = null;
    }

    public function editJabatan(int $positionId): void
    {
        $position = Position::findOrFail($positionId);

        $this->authorize('update', $position->orgUnit);

        $this->tutupForm();
        $this->formPositionTampil = true;
        $this->editingPositionId = $position->id;
        $this->positionCode = $position->code;
        $this->positionName = $position->name;
        $this->positionReportsTo = $position->reports_to_position_id;
    }

    public function simpanJabatan(SavePosition $action): void
    {
        $unit = OrgUnit::findOrFail($this->selectedId);

        $this->authorize('update', $unit);

        $this->validate([
            'positionCode' => $this->editingPositionId === null ? ['required', 'string', 'max:30'] : ['nullable'],
            'positionName' => ['required', 'string', 'max:100'],
            'positionReportsTo' => ['nullable', 'integer', 'exists:positions,id'],
        ], [], [
            'positionCode' => 'Kode jabatan',
            'positionName' => 'Nama jabatan',
            'positionReportsTo' => 'Atasan jabatan',
        ]);

        $position = $this->editingPositionId !== null ? Position::findOrFail($this->editingPositionId) : null;

        try {
            $action->handle($position, $unit, [
                'code' => $this->positionCode,
                'name' => $this->positionName,
                'reports_to_position_id' => $this->positionReportsTo,
            ], auth()->user());
        } catch (AccessRuleException $e) {
            $this->addError(in_array($e->rule, ['A-344', 'A-387'], true) ? 'positionReportsTo' : 'positionName', $e->getMessage());

            return;
        }

        $this->tutupForm();
        $this->dispatch('pesan', teks: 'Jabatan disimpan.');
    }

    public function nonaktifkanJabatan(int $positionId, SavePosition $action): void
    {
        $position = Position::findOrFail($positionId);

        $this->authorize('update', $position->orgUnit);

        try {
            $action->deactivate($position, auth()->user());
        } catch (AccessRuleException $e) {
            $this->dispatch('pesan', teks: $e->getMessage(), jenis: 'danger');

            return;
        }

        $this->dispatch('pesan', teks: 'Jabatan '.$position->name.' dinonaktifkan.');
    }

    public function aktifkanJabatan(int $positionId, SavePosition $action): void
    {
        $position = Position::findOrFail($positionId);

        $this->authorize('update', $position->orgUnit);

        $action->reactivate($position, auth()->user());

        $this->dispatch('pesan', teks: 'Jabatan '.$position->name.' diaktifkan kembali.');
    }

    // ------------------------------------------------------------------ render

    public function render(): View
    {
        $units = OrgUnit::query()->orderBy('name')->get();
        $selected = $this->selectedId !== null ? $units->firstWhere('id', $this->selectedId) : null;
        $anggota = $selected === null ? collect() : User::query()
            ->with(['position'])
            ->where('org_unit_id', $selected->id)
            ->orderBy('name')->get();

        return view('livewire.access.org-tree', [
            'pohon' => $this->ratakan($units),
            'units' => $units,
            'selected' => $selected,
            'positions' => $selected === null ? collect() : Position::query()
                ->with('reportsTo:id,name')
                ->where('org_unit_id', $selected->id)
                ->orderBy('level')->orderBy('name')->get(),
            'anggota' => $anggota,
            'atasan' => $this->atasanAnggota($anggota),
            // Calon atasan: jabatan aktif mana pun selain yang sedang diubah (A-344).
            'pilihanAtasan' => ! $this->formPositionTampil ? collect() : Position::query()
                ->with('orgUnit:id,name')
                ->where('is_active', true)
                ->when($this->editingPositionId !== null, fn ($q) => $q->whereKeyNot($this->editingPositionId))
                ->orderBy('level')->orderBy('name')->get(['id', 'name', 'org_unit_id', 'level']),
        ]);
    }

    /**
     * Atasan efektif tiap anggota beserta asalnya (A-345).
     *
     * @param  Collection<int, User>  $anggota
     * @return array<int, array{nama: array<int, string>, sumber: string|null}>
     */
    private function atasanAnggota(Collection $anggota): array
    {
        $atasan = app(Atasan::class);
        $hasil = [];

        foreach ($anggota as $orang) {
            $ids = $atasan->dari((int) $orang->id);
            $hasil[$orang->id] = [
                'nama' => $ids === [] ? [] : User::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all(),
                'sumber' => $atasan->sumber($orang),
            ];
        }

        return $hasil;
    }

    /**
     * Meratakan pohon unit menjadi daftar berurutan dengan kedalaman,
     * supaya mudah digambar tanpa rekursi di Blade.
     *
     * @param  Collection<int, OrgUnit>  $units
     * @return Collection<int, array{unit: OrgUnit, depth: int}>
     */
    private function ratakan(Collection $units, ?int $parentId = null, int $depth = 0): Collection
    {
        $hasil = collect();

        foreach ($units->where('parent_id', $parentId) as $unit) {
            $hasil->push(['unit' => $unit, 'depth' => $depth]);
            $hasil = $hasil->concat($this->ratakan($units, $unit->id, $depth + 1));
        }

        return $hasil;
    }
}
