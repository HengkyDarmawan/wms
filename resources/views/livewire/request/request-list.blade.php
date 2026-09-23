<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Permintaan material') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Dokumen niat: menyatakan kebutuhan proyek, belum memindahkan stok.') }}
            </p>
        </div>
        @can('create', App\Domain\Request\Models\MaterialRequest::class)
            <a class="btn btn-primary" href="{{ route('requests.create') }}">{{ __('Permintaan baru') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label" for="cari-req">{{ __('Cari') }}</label>
                <input class="form-control" id="cari-req" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nomor REQ atau proyek') }}">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-status-req">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-req" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-proyek-req">{{ __('Proyek') }}</label>
                <select class="form-select" id="filter-proyek-req" wire:model.live="projectFilter">
                    <option value="">{{ __('Semua proyek') }}</option>
                    @foreach ($projects as $proyek)
                        <option value="{{ $proyek->id }}">{{ $proyek->code }} — {{ $proyek->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="filter-pemohon-req">{{ __('Pemohon') }}</label>
                <select class="form-select" id="filter-pemohon-req" wire:model.live="requesterTypeFilter">
                    <option value="">{{ __('Semua') }}</option>
                    <option value="internal">{{ __('Internal') }}</option>
                    <option value="client">{{ __('Klien') }}</option>
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="filter-terbuka" type="checkbox" wire:model.live="hanyaTerbuka">
                    <label class="form-check-label" for="filter-terbuka">{{ __('Hanya terbuka') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" id="filter-terlambat" type="checkbox" wire:model.live="hanyaTerlambat">
                    <label class="form-check-label" for="filter-terlambat">{{ __('SLA terlampaui') }}</label>
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
                        <th scope="col">{{ __('Pemohon') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Dibutuhkan') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        @php $terlambat = $r->status->value === 'under_review' && $r->reviewAgeInDays() >= $sla; @endphp
                        <tr @class(['table-warning' => $terlambat])>
                            <td>
                                <a href="{{ route('requests.show', $r) }}">{{ $r->number }}</a>
                                @if ($r->isSupplement())
                                    <span class="badge text-bg-info">{{ __('Tambahan') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $r->project?->code }}
                                <div class="small text-muted">{{ $r->project?->name }}</div>
                            </td>
                            <td>
                                {{ $r->requester?->name ?? '—' }}
                                <div class="small text-muted">{{ $r->requester_type->label() }}</div>
                            </td>
                            <td class="text-end">{{ $r->open_lines_count }}</td>
                            <td class="text-nowrap">
                                {{ $r->required_date?->format('d/m/Y') }}
                                @if ($terlambat)
                                    <div class="small text-danger">
                                        {{ __('Ditinjau :n hari', ['n' => $r->reviewAgeInDays()]) }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ __('Belum ada permintaan yang cocok dengan penyaring ini.') }}
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
