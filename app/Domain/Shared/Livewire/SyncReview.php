<?php

declare(strict_types=1);

namespace App\Domain\Shared\Livewire;

use App\Domain\Count\Models\CountAssignment;
use App\Domain\Shared\Models\SyncItem;
use App\Domain\Shared\Support\OfflineSync;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * **Antrean tinjauan offline** `/offline/tinjauan` (32-pwa-offline, BR-OPN-03,
 * A-438): transaksi dari perangkat yang bentrok aturan tidak diposting otomatis;
 * Kepala Gudang/Auditor memutus — coba lagi (atas nama pengirim) atau tolak.
 */
class SyncReview extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: 'review')]
    public string $status = 'review';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('count.approve'), 403);
    }

    public function render(): View
    {
        $daftar = SyncItem::query()->with('user:id,name', 'decider:id,name')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')->paginate(25);

        return view('livewire.shared.sync-review', [
            'daftar' => $daftar,
            'tugas' => CountAssignment::query()->with('bin:id,code', 'stockCount:id,number')
                ->whereIn('id', $daftar->getCollection()->where('kind', 'count_record')->map(fn ($i) => (int) ($i->payload['assignment_id'] ?? 0)))
                ->get()->keyBy('id'),
            'label' => SyncItem::LABEL,
        ]);
    }

    public function cobaLagi(int $id, OfflineSync $sync): void
    {
        $item = SyncItem::query()->whereIn('status', [SyncItem::REVIEW])->findOrFail($id);
        $sync->proses($item, $item->user);
        $item->update(['decided_by' => auth()->id(), 'decided_at' => now()]);
        $this->dispatch('pesan', teks: $item->status === SyncItem::POSTED ? __('Transaksi diposting.') : __('Masih bentrok: :m', ['m' => $item->message]), jenis: $item->status === SyncItem::POSTED ? 'success' : 'warning');
    }

    public function tolak(int $id): void
    {
        $item = SyncItem::query()->where('status', SyncItem::REVIEW)->findOrFail($id);
        $item->update(['status' => SyncItem::REJECTED, 'decided_by' => auth()->id(), 'decided_at' => now(), 'message' => mb_substr('Ditolak peninjau. '.($item->message ?? ''), 0, 255)]);
        app(OfflineSync::class)->buangBerkas($item);
        activity('sync')->performedOn($item)->causedBy(auth()->user())->log('Transaksi offline ditolak peninjau');
        $this->dispatch('pesan', teks: __('Transaksi ditolak; tidak diposting.'));
    }
}
