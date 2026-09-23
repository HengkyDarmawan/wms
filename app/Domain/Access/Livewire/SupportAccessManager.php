<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\GrantSupportAccess;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SupportAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 10-access §6.7 — Admin Company memberi Super Admin izin sementara untuk
 * membuka data operasional company (A-27, BR-SUB-04). Tanpa izin ini Super Admin
 * tidak bisa melihat data company, dan setiap pemberian tercatat di audit log.
 */
class SupportAccessManager extends Component
{
    public ?int $platformUserId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('support_access.grant');

        $this->platformUserId = PlatformUser::query()->orderBy('name')->value('id');
        $this->isiWaktuBawaan();
    }

    private function isiWaktuBawaan(): void
    {
        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        $this->startsAt = now($zona)->format('Y-m-d\TH:i');
        $this->endsAt = now($zona)->addDays(2)->format('Y-m-d\TH:i');
    }

    public function beri(GrantSupportAccess $action): void
    {
        $this->authorize('support_access.grant');

        $data = $this->validate([
            'platformUserId' => ['required', 'integer'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'reason' => ['required', 'string', 'max:255'],
        ], [], [
            'platformUserId' => 'Super Admin',
            'startsAt' => 'Mulai',
            'endsAt' => 'Selesai',
            'reason' => 'Alasan',
        ]);

        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        /** @var User $admin */
        $admin = auth()->user();

        try {
            $action->handle(
                tenant(),
                (int) $data['platformUserId'],
                Carbon::parse($data['startsAt'], $zona),
                Carbon::parse($data['endsAt'], $zona),
                $data['reason'],
                $admin,
            );
        } catch (AccessRuleException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->reason = '';
        $this->isiWaktuBawaan();
        $this->dispatch('pesan', teks: 'Akses dukungan diberikan.');
    }

    public function cabut(int $accessId, GrantSupportAccess $action): void
    {
        $this->authorize('support_access.revoke');

        // Hanya izin milik company ini yang boleh dicabut dari layar ini.
        $akses = SupportAccess::query()
            ->where('company_id', tenant()?->getTenantKey())
            ->find($accessId);

        if ($akses === null) {
            $this->dispatch('pesan', teks: 'Akses dukungan tidak ditemukan untuk company ini.', jenis: 'danger');

            return;
        }

        $action->revoke($akses, auth()->user());

        $this->dispatch('pesan', teks: 'Akses dukungan dicabut.');
    }

    public function render(): View
    {
        return view('livewire.access.support-access', [
            'superAdmins' => PlatformUser::query()->orderBy('name')->get(),
            'berlaku' => $this->daftar(aktifSaja: true),
            'riwayat' => $this->daftar(aktifSaja: false),
            'maksHari' => (int) config('access.support_access.max_days', 7),
        ]);
    }

    /** @return Collection<int, SupportAccess> */
    private function daftar(bool $aktifSaja): Collection
    {
        return SupportAccess::query()
            ->with('platformUser')
            ->where('company_id', tenant()?->getTenantKey())
            ->when($aktifSaja, fn ($q) => $q->active())
            ->latest('id')
            ->limit($aktifSaja ? 20 : 30)
            ->get();
    }
}
