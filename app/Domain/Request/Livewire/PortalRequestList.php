<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 14-request §6 — daftar REQ di portal klien.
 *
 * Yang ditonjolkan berbeda dari layar internal: klien tidak peduli gudang mana
 * yang mengirim, ia peduli **kapan barangnya datang** dan **apakah ada yang
 * menunggu tanggapannya**. Keduanya jadi kolom pertama.
 */
class PortalRequestList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: false)]
    public bool $hanyaPerluTanggapan = false;

    public function mount(): void
    {
        $this->authorize('viewAny', MaterialRequest::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.request.portal-request-list', [
            'requests' => $this->daftar(),
            'statuses' => MaterialRequestStatus::options(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        $klienId = auth()->user()?->client_id;

        return MaterialRequest::query()
            ->with('project:id,code,name')
            ->withCount([
                'lines as open_lines_count' => fn (Builder $q) => $q->open(),
                // Dua angka yang memicu tindakan klien, dihitung di query
                // supaya daftar sepanjang apa pun tetap satu perjalanan.
                'lines as pending_substitution_count' => fn (Builder $q) => $q
                    ->open()
                    ->whereNotNull('substituted_at')
                    ->whereNull('substitution_response'),
            ])
            // Portal hanya menampilkan REQ proyek klien yang sedang masuk.
            ->whereHas('project', fn (Builder $p) => $p->where('client_id', $klienId))
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->hanyaPerluTanggapan, fn (Builder $q) => $q
                ->whereHas('lines', fn (Builder $l) => $l
                    ->open()
                    ->whereNotNull('substituted_at')
                    ->whereNull('substitution_response')))
            ->orderByDesc('id')
            ->paginate(20);
    }

    /** Tanggal janji paling jauh: itulah yang ditunggu klien. */
    public function janjiTerjauh(MaterialRequest $request): ?string
    {
        $tanggal = MaterialRequestLine::query()
            ->where('material_request_id', $request->id)
            ->open()
            ->max('promised_date');

        return $tanggal === null ? null : (string) $tanggal;
    }
}
