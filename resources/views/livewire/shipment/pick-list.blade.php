<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Tugas picking') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Mengambil barang dari bin ke Loading Area. Di sinilah stok benar-benar mulai bergerak.') }}
            </p>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    @if ($menunggu->isNotEmpty())
        <div class="card border-primary mb-3">
            <div class="card-header">
                <strong>{{ __('Menunggu dibuatkan tugas') }}</strong>
                <span class="text-muted small ms-2">
                    {{ __('REQ disetujui yang barisnya belum masuk tugas picking mana pun.') }}
                </span>
            </div>
            <ul class="list-group list-group-flush">
                @foreach ($menunggu as $req)
                    <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <a href="{{ route('requests.show', $req) }}">{{ $req->number }}</a>
                            <span class="text-muted small">
                                · {{ $req->project?->code }} · {{ __('dibutuhkan') }}
                                {{ $req->required_date?->format('d/m/Y') }}
                            </span>
                        </div>
                        <button class="btn btn-sm btn-primary" type="button"
                                wire:click="buatDariReq({{ $req->id }})">
                            {{ __('Buat tugas picking') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="cari-pck">{{ __('Cari nomor') }}</label>
                <input class="form-control" id="cari-pck" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="PCK/…">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-gudang-pck">{{ __('Gudang') }}</label>
                <select class="form-select" id="filter-gudang-pck" wire:model.live="warehouseFilter">
                    <option value="">{{ __('Semua gudang') }}</option>
                    @foreach ($warehouses as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->code }} — {{ $gudang->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-pck">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-pck" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" id="filter-terbuka-pck" type="checkbox"
                           wire:model.live="hanyaTerbuka">
                    <label class="form-check-label" for="filter-terbuka-pck">{{ __('Hanya terbuka') }}</label>
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
                        <th scope="col">{{ __('Gudang') }}</th>
                        <th class="text-end" scope="col">{{ __('Baris') }}</th>
                        <th scope="col">{{ __('Petugas') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $t)
                        <tr>
                            <td><a href="{{ route('picks.show', $t) }}">{{ $t->number }}</a></td>
                            <td>
                                {{ $t->warehouse?->code }}
                                <div class="small text-muted">{{ $t->warehouse?->name }}</div>
                            </td>
                            <td class="text-end">{{ $t->lines_count }}</td>
                            <td>{{ $t->assignee?->name ?? '—' }}</td>
                            <td><span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="5">
                                {{ __('Belum ada tugas picking yang cocok dengan penyaring ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tasks->hasPages())
            <div class="card-footer">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
