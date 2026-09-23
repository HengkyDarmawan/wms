<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Permintaan saya') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Permintaan material proyek Anda beserta tanggal janji gudang.') }}
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label" for="cari-portal-req">{{ __('Cari nomor') }}</label>
                <input class="form-control" id="cari-portal-req" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="REQ/…">
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="filter-status-portal">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-portal" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <div class="form-check">
                    <input class="form-check-input" id="filter-tanggapan" type="checkbox"
                           wire:model.live="hanyaPerluTanggapan">
                    <label class="form-check-label" for="filter-tanggapan">
                        {{ __('Menunggu tanggapan saya') }}
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Nomor') }}</th>
                        <th scope="col">{{ __('Proyek') }}</th>
                        <th scope="col">{{ __('Tanggal janji') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr @class(['table-warning' => $r->pending_substitution_count > 0])>
                            <td>
                                <a href="{{ route('portal.requests.show', $r) }}">{{ $r->number }}</a>
                                @if ($r->isSupplement())
                                    <span class="badge text-bg-info">{{ __('Tambahan') }}</span>
                                @endif
                                @if ($r->pending_substitution_count > 0)
                                    <div class="small text-danger">
                                        {{ __(':n baris menunggu tanggapan Anda', ['n' => $r->pending_substitution_count]) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                {{ $r->project?->code }}
                                <div class="small text-muted">{{ $r->project?->name }}</div>
                            </td>
                            <td class="text-nowrap">
                                {{ $this->janjiTerjauh($r) ?? __('belum dijanjikan') }}
                            </td>
                            <td class="text-end">{{ $r->open_lines_count }}</td>
                            <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">
                                {{ __('Belum ada permintaan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
