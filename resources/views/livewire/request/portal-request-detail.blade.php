<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $req->number }}
                <span class="badge {{ $req->status->badge() }}">{{ $req->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $req->project?->code }} — {{ $req->project?->name }} ·
                {{ __('Dibutuhkan') }}: {{ $req->required_date?->format('d/m/Y') }}
                @if ($req->parent)
                    · {{ __('Tambahan dari') }}
                    <a href="{{ route('portal.requests.show', $req->parent) }}">{{ $req->parent->number }}</a>
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('portal.requests.index') }}">{{ __('Kembali') }}</a>
            @can('addLines', $req)
                <button class="btn btn-primary" type="button" wire:click="mintaTambah">{{ __('Tambah baris') }}</button>
            @endcan
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

    @if ($dialog === 'tambah')
        <div class="card border-primary mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>{{ __('Tambah baris permintaan') }}</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBaris">
                    {{ __('Baris lagi') }}
                </button>
            </div>
            <div class="card-body">
                @if ($req->status->hasReservations())
                    <div class="alert alert-info">
                        {{ __('Permintaan ini sudah disetujui, jadi tambahan Anda akan menjadi REQ Tambahan dengan nomor sendiri.') }}
                    </div>
                @endif

                @foreach ($barisBaru as $i => $baris)
                    <div class="row g-2 mb-2" wire:key="portal-baris-{{ $i }}">
                        <div class="col-md-5">
                            <select class="form-select" wire:model="barisBaru.{{ $i }}.item_id">
                                <option value="">{{ __('— tidak dari katalog —') }}</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input class="form-control" type="text" wire:model="barisBaru.{{ $i }}.non_catalog_text"
                                   placeholder="{{ __('Atau tulis nama barang') }}">
                        </div>
                        <div class="col-md-3">
                            <input class="form-control" type="number" step="0.0001" min="0"
                                   wire:model="barisBaru.{{ $i }}.qty_base" placeholder="{{ __('Jumlah') }}">
                        </div>
                    </div>
                @endforeach

                @error('form.item_id') <div class="text-danger small">{{ $message }}</div> @enderror
                @error('form.qty_base') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanTambahan">{{ __('Kirim') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if (in_array($dialog, ['tolak-ganti', 'minta-batal'], true))
        <div class="card border-warning mb-3">
            <div class="card-header">
                <strong>
                    {{ $dialog === 'tolak-ganti' ? __('Tolak penggantian item') : __('Minta pembatalan baris') }}
                </strong>
            </div>
            <div class="card-body">
                <label class="form-label" for="portal-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('reasonCode') is-invalid @enderror" id="portal-alasan"
                        wire:model="reasonCode">
                    <option value="">{{ __('Pilih alasan…') }}</option>
                    @foreach ($alasan as $kode => $label)
                        <option value="{{ $kode }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="jalankanDialogAlasan">{{ __('Kirim') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris permintaan') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Barang') }}</th>
                        <th class="text-end" scope="col">{{ __('Jumlah') }}</th>
                        <th class="text-end" scope="col" title="{{ __('Terkirim / diterima baik') }}">{{ __('Terkirim / Diterima') }}</th>
                        <th scope="col">{{ __('Tanggal janji') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $l)
                        <tr wire:key="portal-detail-{{ $l->id }}"
                            @class([
                                'table-secondary' => $l->status->value === 'cancelled',
                                'table-warning' => $l->substitutionIsPending(),
                            ])>
                            <td>
                                {{ $l->displayName() }}
                                @if ($l->isSubstituted())
                                    <div class="small text-muted">
                                        {{ __('Anda meminta') }}: {{ $l->original_item_text }}
                                        @if ($l->substitution_response)
                                            · {{ $l->substitution_response->label() }}
                                        @elseif ($l->substitution_deadline_at)
                                            · {{ __('tanggapi sebelum :tgl', ['tgl' => $l->substitution_deadline_at->format('d/m/Y H:i')]) }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_base, 2, ',', '.') }}</td>
                            <td class="text-end text-nowrap">
                                {{ number_format((float) $l->qty_shipped, 2, ',', '.') }}
                                <span class="text-muted">/</span>
                                {{ number_format((float) $l->qty_received, 2, ',', '.') }}
                            </td>
                            <td class="text-nowrap">{{ $l->promised_date?->format('d/m/Y') ?? __('belum dijanjikan') }}</td>
                            <td>
                                <span class="badge text-bg-light">{{ $l->status->label() }}</span>
                                @if ($l->awaitsCancelConfirmation())
                                    <div class="small text-muted">{{ __('Menunggu konfirmasi gudang') }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($l->substitutionIsPending())
                                    @can('respondSubstitution', $req)
                                        <button class="btn btn-sm btn-success" type="button"
                                                wire:click="setujuiPenggantian({{ $l->id }})">
                                            {{ __('Setuju') }}
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaTolakPenggantian({{ $l->id }})">
                                            {{ __('Tolak') }}
                                        </button>
                                    @endcan
                                @elseif ($l->status->value === 'open' && ! $l->awaitsCancelConfirmation())
                                    @can('requestCancel', $req)
                                        <button class="btn btn-sm btn-outline-danger" type="button"
                                                wire:click="mintaBatalBaris({{ $l->id }})">
                                            {{ __('Minta batal') }}
                                        </button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada baris.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($supplements->isNotEmpty())
        <div class="card">
            <div class="card-header"><strong>{{ __('Permintaan tambahan') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($supplements as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('portal.requests.show', $s->id) }}">{{ $s->number }}</a>
                        <span class="badge {{ $s->status->badge() }}">{{ $s->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
