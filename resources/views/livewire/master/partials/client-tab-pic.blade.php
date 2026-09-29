{{-- Tab PIC klien (A-326): orang dari pihak klien, bukan PIC proyek kita sendiri. --}}
@if ($showForm)
    <div class="card mb-3">
        <div class="card-header">
            <strong>{{ $editingId ? __('Ubah PIC klien') : __('PIC klien baru') }}</strong>
            <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="pic-nama">{{ __('Nama') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('formPic.name') is-invalid @enderror" id="pic-nama"
                           type="text" wire:model="formPic.name" maxlength="100">
                    @error('formPic.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="pic-jabatan">{{ __('Jabatan') }}</label>
                    <input class="form-control" id="pic-jabatan" type="text" wire:model="formPic.position" maxlength="100">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="pic-wa">{{ __('No. WA') }}</label>
                    <input class="form-control @error('formPic.phone') is-invalid @enderror" id="pic-wa"
                           type="text" wire:model="formPic.phone" placeholder="0812-3456-7890" maxlength="20">
                    @error('formPic.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="pic-email">{{ __('Email') }}</label>
                    <input class="form-control @error('formPic.email') is-invalid @enderror" id="pic-email"
                           type="email" wire:model="formPic.email" maxlength="150">
                    <div class="form-text">{{ __('Wajib bila PIC ini akan diberi akun portal.') }}</div>
                    @error('formPic.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-7">
                    <label class="form-label">{{ __('Proyek yang diurus') }}</label>
                    <div class="border rounded p-2 @error('formPic.projects') border-danger @enderror">
                        @forelse ($proyekPilihan as $proyek)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pic-proyek-{{ $proyek->id }}"
                                       value="{{ $proyek->id }}" wire:model="formPic.projects">
                                <label class="form-check-label" for="pic-proyek-{{ $proyek->id }}">
                                    {{ $proyek->code }} — {{ $proyek->name }}
                                </label>
                            </div>
                        @empty
                            <div class="small text-muted">{{ __('Klien ini belum punya proyek.') }}</div>
                        @endforelse
                    </div>
                    @error('formPic.projects') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    <div class="form-text">
                        {{ __('Keterangan kontak — siapa mengurus apa. Hak lihat portal diatur lewat Tim site di halaman proyek.') }}
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="pic-catatan">{{ __('Catatan') }}</label>
                    <input class="form-control" id="pic-catatan" type="text" wire:model="formPic.notes" maxlength="255">
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="button" wire:click="simpanPic">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalPic">{{ __('Batal') }}</button>
            </div>
        </div>
    </div>
@endif

@if ($picNonaktif !== null)
    <div class="card border-danger mb-3">
        <div class="card-header text-danger">
            <strong>{{ __('Nonaktifkan PIC') }} "{{ $picNonaktif->name }}"</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="pic-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="pic-alasan" wire:model="reasonCode">
                        <option value="">{{ __('— pilih —') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="pic-keterangan">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="pic-keterangan" type="text" wire:model="reasonNotes" maxlength="255">
                </div>

                @if ($picNonaktif->portalUser !== null)
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="pic-nonaktif-akun" wire:model="nonaktifkanAkun">
                            <label class="form-check-label" for="pic-nonaktif-akun">
                                {{ __('Nonaktifkan juga akun portalnya') }}
                                ({{ $picNonaktif->portalUser->name }} · {{ $picNonaktif->portalUser->email }})
                            </label>
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-danger" type="button" wire:click="nonaktifkanPic">{{ __('Nonaktifkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalNonaktifPic">{{ __('Batal') }}</button>
            </div>
        </div>
    </div>
@endif

@foreach ($perluSelaras as $picId => $kodeProyek)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" wire:key="selaras-{{ $picId }}">
        <div class="me-auto">
            {{ __('Proyek PIC ini bertambah (:kode) tetapi akun portalnya belum melihatnya.', ['kode' => $kodeProyek]) }}
        </div>
        @can('assignRole', \App\Domain\Access\Models\User::class)
            <button class="btn btn-sm btn-primary" type="button" wire:click="selaraskanTimSite({{ $picId }})">
                {{ __('Selaraskan Tim site') }}
            </button>
        @endcan
    </div>
@endforeach

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong>{{ __('PIC klien') }}</strong>
            <span class="text-muted small ms-2">{{ __('Orang dari pihak klien — bukan staf kita.') }}</span>
        </div>
        @can('create', \App\Domain\Master\Models\Client::class)
            <button class="btn btn-sm btn-primary" type="button" wire:click="buatPic">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah PIC') }}
            </button>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Jabatan') }}</th>
                    <th>{{ __('Kontak') }}</th>
                    <th>{{ __('Proyek') }}</th>
                    <th>{{ __('Akun portal') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data as $pic)
                    <tr wire:key="pic-{{ $pic->id }}">
                        <td class="fw-semibold">{{ $pic->name }}</td>
                        <td>{{ $pic->position ?: '—' }}</td>
                        <td>
                            <div>{{ $pic->phone ?: '—' }}</div>
                            <div class="small text-muted">{{ $pic->email ?: '—' }}</div>
                        </td>
                        <td class="small">{{ $pic->projects->pluck('code')->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($pic->portalUser !== null)
                                <a href="{{ route('users.show', $pic->portalUser->id) }}">{{ $pic->portalUser->name }}</a>
                                <span class="badge text-bg-{{ $pic->portalUser->is_active ? 'success' : 'secondary' }}">
                                    {{ $pic->portalUser->is_active ? __('Aktif') : __('Nonaktif') }}
                                </span>
                            @elseif ($pic->is_active)
                                @can('create', \App\Domain\Access\Models\User::class)
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="buatAkunPortal({{ $pic->id }})">
                                        <i class="bi bi-box-arrow-in-right"></i> {{ __('Buat akun portal') }}
                                    </button>
                                @endcan
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $pic->is_active ? 'success' : 'secondary' }}">
                                {{ $pic->is_active ? __('Aktif') : __('Nonaktif') }}
                            </span>
                        </td>
                        <td class="text-end">
                            @can('update', $client)
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        wire:click="ubahPic({{ $pic->id }})">{{ __('Ubah') }}</button>
                            @endcan

                            @if ($pic->is_active)
                                @can('deactivate', $client)
                                    <button class="btn btn-sm btn-outline-danger" type="button"
                                            wire:click="mintaNonaktifPic({{ $pic->id }})">{{ __('Nonaktifkan') }}</button>
                                @endcan
                            @else
                                @can('update', $client)
                                    <button class="btn btn-sm btn-outline-success" type="button"
                                            wire:click="aktifkanPic({{ $pic->id }})">{{ __('Aktifkan') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">{{ __('Belum ada PIC untuk klien ini.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($data->hasPages())
        <div class="card-footer">{{ $data->links() }}</div>
    @endif
</div>
