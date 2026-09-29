<div>
    {{-- Tab Tim site di hub proyek (A-337): akses berbatas waktu, satu-satunya tempat tanggal. --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div class="small text-muted">
            {{ __('Gudang Site proyek ini') }}:
            <strong>{{ $sites->pluck('code')->implode(', ') ?: __('belum ada') }}</strong>
            @if ($project->target_end_date)
                · {{ __('target selesai proyek') }} {{ $project->target_end_date->format('d/m/Y') }}
            @endif
        </div>

        @if ($bolehAtur)
            <button class="btn btn-sm btn-primary" type="button" wire:click="tambah">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah anggota') }}
            </button>
        @endif
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    @if ($showForm)
        <div class="card mb-3">
            <div class="card-header">
                <strong>{{ __('Tambah anggota Tim site') }}</strong>
                <span class="text-muted small ms-2">{{ __('Field bertanda * wajib diisi.') }}</span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    {{-- A-388: staf kita & akun portal klien proyek ini, dicari ke server (A-384). --}}
                    <x-pilih model="userId" id="tim-orang" server wajib :label="__('Orang')" :kosong="__('— pilih —')"
                             :kelompok="[__('Staf kita'), __('Akun portal klien')]" :options="$calon"
                             :placeholder="__('Cari nama, email, jabatan, atau unit…')" />
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="tim-peran">{{ __('Peran di site') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('roleId') is-invalid @enderror" id="tim-peran" wire:model="roleId">
                        <option value="">{{ __('— pilih —') }}</option>
                        @foreach ($peranPilihan as $peran)
                            <option value="{{ $peran->id }}">{{ $peran->name }}</option>
                        @endforeach
                    </select>
                    @error('roleId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="tim-mulai">{{ __('Mulai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('startsOn') is-invalid @enderror" id="tim-mulai" type="date"
                           wire:model="startsOn">
                    @error('startsOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="tim-selesai">{{ __('Selesai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('endsOn') is-invalid @enderror" id="tim-selesai" type="date"
                           wire:model="endsOn">
                    @error('endsOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 small text-muted">
                    {{ __('Peran gudang mendapat cakupan tiap Gudang Site proyek ini; peran proyek mendapat cakupan proyeknya. Lewat tanggal selesai, akses ke site itu saja yang berhenti.') }}
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="simpanTambah">{{ __('Simpan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batal">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($perpanjangkan !== null)
        <div class="card border-primary mb-3">
            <div class="card-header"><strong>{{ __('Perpanjang') }} — {{ $perpanjangkan->user?->name }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Selesai sekarang') }}</label>
                    <input class="form-control" type="text" value="{{ $perpanjangkan->ends_on->format('d/m/Y') }}" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tim-selesai-baru">{{ __('Selesai baru') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('newEndsOn') is-invalid @enderror" id="tim-selesai-baru"
                           type="date" wire:model="newEndsOn">
                    @error('newEndsOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tim-catatan">{{ __('Catatan') }}</label>
                    <input class="form-control" id="tim-catatan" type="text" wire:model="extendNotes" maxlength="255">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary" type="button" wire:click="perpanjang">{{ __('Perpanjang') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batal">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    @if ($akhirkan !== null)
        <div class="card border-danger mb-3">
            <div class="card-header text-danger">
                <strong>{{ __('Akhiri lebih awal') }} — {{ $akhirkan->user?->name }}</strong>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="tim-sampai">{{ __('Berlaku sampai') }} <span class="wajib">*</span></label>
                    <input class="form-control @error('endsOnAkhir') is-invalid @enderror" id="tim-sampai"
                           type="date" wire:model="endsOnAkhir">
                    @error('endsOnAkhir') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tim-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('reasonCode') is-invalid @enderror" id="tim-alasan" wire:model="reasonCode">
                        <option value="">{{ __('— pilih —') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="tim-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="tim-ket" type="text" wire:model="reasonNotes" maxlength="255">
                </div>
                <div class="col-12 small text-muted">
                    {{ __('Akses ke Gudang Site proyek ini berhenti setelah tanggal itu. Akun dan akses lain orang itu tetap aktif.') }}
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="akhiri">{{ __('Akhiri') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batal">{{ __('Batal') }}</button>
            </div>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Pihak') }}</th>
                    <th>{{ __('Peran di site') }}</th>
                    <th>{{ __('Mulai') }}</th>
                    <th>{{ __('Selesai') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($anggota as $baris)
                    @php($status = $baris->status())
                    <tr wire:key="tim-{{ $baris->id }}">
                        <td class="fw-semibold">{{ $baris->user?->name ?? '—' }}</td>
                        <td>{{ $baris->user?->client_id === null ? __('Kita') : __('PIC klien') }}</td>
                        <td>{{ $baris->role?->name ?? '—' }}</td>
                        <td>{{ $baris->starts_on->format('d/m/Y') }}</td>
                        <td>{{ $baris->ends_on->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $status->badge() }}">{{ $status->label() }}</span>
                            @if ($status === \App\Domain\Access\Enums\SiteTeamStatus::EndingSoon)
                                <div class="small text-muted">{{ __(':n hari lagi', ['n' => $baris->sisaHari()]) }}</div>
                            @elseif ($baris->ended_at !== null && $baris->endReason !== null)
                                <div class="small text-muted">{{ $baris->endReason->label }}</div>
                            @endif
                            @unless ($baris->grants_access)
                                <div class="small text-muted">{{ __('Sudah punya akses tetap; ini catatan saja.') }}</div>
                            @endunless
                        </td>
                        <td class="text-end">
                            @if ($status->isRunning() || $status === \App\Domain\Access\Enums\SiteTeamStatus::Upcoming)
                                @if ($bolehPerpanjang)
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="mintaPerpanjang({{ $baris->id }})">{{ __('Perpanjang') }}</button>
                                @endif
                                @if ($bolehAtur)
                                    <button class="btn btn-sm btn-outline-danger" type="button"
                                            wire:click="mintaAkhiri({{ $baris->id }})">{{ __('Akhiri lebih awal') }}</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ __('Belum ada anggota Tim site untuk proyek ini.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="small text-muted mt-2 mb-0">
        {{ __('Riwayat anggota tidak pernah dihapus; penempatan yang berakhir tetap tercatat di sini.') }}
    </p>
</div>
