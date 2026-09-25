<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Proyek') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Pusat pelacakan material. Buka proyek untuk melihat stok on-site, dokumen, approval, dan menutup proyek. Hanya proyek aktif yang menerima dokumen baru.') }}
            </p>
        </div>

        @can('create', \App\Domain\Master\Models\Project::class)
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('imports.index') }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i> {{ __('Impor Excel') }}
                </a>
                <button class="btn btn-primary" type="button" wire:click="buat">
                    <i class="bi bi-plus-lg"></i> {{ __('Tambah proyek') }}
                </button>
            </div>
        @endcan
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ $editingId ? __('Ubah proyek') : __('Proyek baru') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="proyek-kode">{{ __('Kode') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.code') is-invalid @enderror" id="proyek-kode"
                               type="text" wire:model="form.code" @disabled($editingId !== null)>
                        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="proyek-nama">{{ __('Nama proyek') }} <span class="wajib">*</span></label>
                        <input class="form-control @error('form.name') is-invalid @enderror" id="proyek-nama"
                               type="text" wire:model="form.name">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" id="proyek-internal" type="checkbox"
                                   wire:model.live="form.is_internal">
                            <label class="form-check-label" for="proyek-internal">
                                {{ __('Proyek Internal (tanpa klien)') }}
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="proyek-klien">
                            {{ __('Klien') }} @unless ($form['is_internal']) <span class="wajib">*</span> @endunless
                        </label>
                        <select class="form-select @error('form.client_id') is-invalid @enderror" id="proyek-klien"
                                wire:model="form.client_id" @disabled($form['is_internal'])>
                            <option value="">{{ __('Pilih klien…') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                        @error('form.client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="proyek-pic">{{ __('PIC proyek') }}</label>
                        <select class="form-select" id="proyek-pic" wire:model="form.pic_user_id">
                            <option value="">{{ __('Belum ditentukan') }}</option>
                            @foreach ($pics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="proyek-mulai">{{ __('Tanggal mulai') }}</label>
                        <input class="form-control" id="proyek-mulai" type="date" wire:model="form.start_date">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="proyek-selesai">{{ __('Target selesai') }}</label>
                        <input class="form-control @error('form.target_end_date') is-invalid @enderror"
                               id="proyek-selesai" type="date" wire:model="form.target_end_date">
                        @error('form.target_end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="proyek-lat">{{ __('Lintang') }}</label>
                        <input class="form-control @error('form.lat') is-invalid @enderror" id="proyek-lat"
                               type="text" wire:model="form.lat" placeholder="-6.2088">
                        @error('form.lat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="proyek-lng">{{ __('Bujur') }}</label>
                        <input class="form-control @error('form.lng') is-invalid @enderror" id="proyek-lng"
                               type="text" wire:model="form.lng" placeholder="106.8456">
                        @error('form.lng') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="proyek-alamat">{{ __('Alamat lokasi') }}</label>
                        <textarea class="form-control" id="proyek-alamat" rows="2" wire:model="form.address"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalForm">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($statusChangingId)
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Ubah status proyek') }}</strong></div>
            <div class="card-body">
                @if ($targetOptions === [])
                    <p class="mb-0 text-muted">{{ __('Status proyek ini tidak bisa diubah lagi.') }}</p>
                @else
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="proyek-status-tujuan">{{ __('Status tujuan') }} <span class="wajib">*</span></label>
                            <select class="form-select @error('targetStatus') is-invalid @enderror"
                                    id="proyek-status-tujuan" wire:model="targetStatus">
                                <option value="">{{ __('Pilih status…') }}</option>
                                @foreach ($targetOptions as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('targetStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="proyek-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                            <select class="form-select @error('reasonCode') is-invalid @enderror" id="proyek-alasan"
                                    wire:model="reasonCode">
                                <option value="">{{ __('Pilih alasan…') }}</option>
                                @foreach ($alasan as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="proyek-keterangan">{{ __('Keterangan') }}</label>
                            <input class="form-control" id="proyek-keterangan" type="text" wire:model="reasonNotes"
                                   placeholder="{{ __('Opsional') }}">
                        </div>
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                @if ($targetOptions !== [])
                    <button class="btn btn-warning" type="button" wire:click="ubahStatus">{{ __('Simpan status') }}</button>
                @endif
                <button class="btn btn-outline-secondary" type="button" wire:click="batalUbahStatus">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label" for="cari-proyek">{{ __('Cari proyek') }}</label>
                <input class="form-control" id="cari-proyek" type="search"
                       wire:model.live.debounce.400ms="search" placeholder="{{ __('Nama atau kode proyek…') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter-klien">{{ __('Klien') }}</label>
                <select class="form-select" id="filter-klien" wire:model.live="clientFilter">
                    <option value="">{{ __('Semua klien') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter-status-proyek">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-proyek" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($statuses as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Proyek') }}</th>
                        <th>{{ __('Klien') }}</th>
                        <th>{{ __('PIC') }}</th>
                        <th>{{ __('Periode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr wire:key="proyek-{{ $project->id }}">
                            <td>
                                <a class="fw-semibold" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                                <div class="small text-muted">{{ $project->code }}</div>
                            </td>
                            <td>
                                @if ($project->is_internal)
                                    <span class="badge text-bg-info">{{ __('Proyek Internal') }}</span>
                                @else
                                    {{ $project->client?->name ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $project->pic?->name ?? '—' }}</td>
                            <td class="small">
                                {{ $project->start_date?->format('d/m/Y') ?? '—' }}
                                &rarr;
                                {{ $project->target_end_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $project->statusBadge() }}">
                                    {{ $project->status->label() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('projects.show', $project) }}">{{ __('Buka') }}</a>
                                @can('update', $project)
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            wire:click="ubah({{ $project->id }})">{{ __('Ubah') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('Belum ada proyek.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($projects->hasPages())
            <div class="card-footer">{{ $projects->links() }}</div>
        @endif
    </div>
</div>
