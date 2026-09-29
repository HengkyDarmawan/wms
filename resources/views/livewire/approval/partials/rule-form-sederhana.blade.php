{{-- Mode sederhana (A-348): tiga langkah, isian lain memakai bawaan (cukup salah satu, 24 jam, web, cadangan otomatis). --}}
<div class="card mb-3">
    <div class="card-header"><strong>{{ __('1. Dokumen apa?') }}</strong></div>
    <div class="card-body">
        <div class="row g-2">
            @foreach ($types as $nilai => $label)
                @php($dipilih = $form['document_type'] === $nilai)
                <div class="col-6 col-md-4 col-xl-3">
                    <button type="button" wire:click="pilihJenis('{{ $nilai }}')" @disabled($ruleId && ! $dipilih)
                            class="btn w-100 text-start h-100 {{ $dipilih ? 'btn-primary' : 'btn-outline-secondary' }}"
                            aria-pressed="{{ $dipilih ? 'true' : 'false' }}">
                        <span class="d-block small opacity-75">{{ \App\Domain\Approval\Enums\ApprovalDocumentType::from($nilai)->code() }}</span>
                        {{ \App\Domain\Approval\Enums\ApprovalDocumentType::from($nilai)->label() }}
                    </button>
                </div>
            @endforeach
        </div>
        @if ($ruleId)
            <div class="form-text">{{ __('Jenis dokumen aturan yang sudah ada tidak bisa diubah; buat aturan baru.') }}</div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>{{ __('2. Siapa yang menyetujui?') }}</strong></div>
    <div class="card-body">
        @error('form.steps') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        @foreach ($steps as $i => $s)
            <div class="border rounded p-2 mb-2" wire:key="lapis-sederhana-{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small text-uppercase text-muted">{{ $i === 0 ? __('Penyetuju pertama') : __('Lalu penyetuju ke-:n', ['n' => $i + 1]) }}</strong>
                    @if ($i > 0)
                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusLapis({{ $i }})">{{ __('Hapus lapis') }}</button>
                    @endif
                </div>
                <div class="row g-2">
                    @foreach ([
                        'direct_manager' => [__('Atasan pemohon'), __('Menurut peta jabatan, atau atasan yang diisi di pengguna.')],
                        'warehouse_head' => [__('Kepala gudang terkait'), __('Kepala gudang dari gudang dokumen. Cocok untuk permintaan klien.')],
                        'role' => [__('Peran tertentu'), __('Mis. Manajemen — siapa pun pemegang perannya.')],
                        'user' => [__('Orang tertentu'), __('Satu nama tetap.')],
                    ] as $jenis => [$judul, $bantuan])
                        @php($aktif = ($s['approver_type'] ?? '') === $jenis)
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded p-2 h-100 {{ $aktif ? 'border-primary bg-primary-subtle' : '' }}">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="lapis-{{ $i }}-{{ $jenis }}" name="lapis-{{ $i }}"
                                           @checked($aktif) wire:click="pilihApprover({{ $i }}, '{{ $jenis }}')">
                                    <label class="form-check-label fw-semibold" for="lapis-{{ $i }}-{{ $jenis }}">{{ $judul }}</label>
                                </div>
                                <div class="small text-muted">{{ $bantuan }}</div>
                                @if ($aktif && $jenis === 'direct_manager')
                                    <select class="form-select form-select-sm mt-1" wire:model.live="steps.{{ $i }}.manager_levels" aria-label="{{ __('Tingkat atasan') }}">
                                        <option value="1">{{ __('1 tingkat di atas') }}</option>
                                        <option value="2">{{ __('2 tingkat di atas') }}</option>
                                    </select>
                                @elseif ($aktif && $jenis === 'role')
                                    <select class="form-select form-select-sm mt-1" wire:model.live="steps.{{ $i }}.approver_ref_id" aria-label="{{ __('Peran') }}">
                                        <option value="">{{ __('Pilih peran…') }}</option>
                                        @foreach ($roles as $r) <option value="{{ $r->id }}">{{ $r->name }}</option> @endforeach
                                    </select>
                                @elseif ($aktif && $jenis === 'user')
                                    <select class="form-select form-select-sm mt-1" wire:model.live="steps.{{ $i }}.approver_ref_id" aria-label="{{ __('Orang') }}">
                                        <option value="">{{ __('Pilih orang…') }}</option>
                                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('form.steps.'.$i.'.approver_ref_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        @endforeach
        @if (count($steps) < 2)
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahLapis"><i class="bi bi-plus-lg"></i> {{ __('Tambah lapis kedua') }}</button>
        @endif
        <div class="form-text">{{ __('Cukup satu orang yang menyetujui per lapis. Lewat 24 jam, tugas naik ke atasan si approver. Ubah di Mode lanjutan bila perlu.') }}</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>{{ __('3. Berlaku kapan?') }}</strong></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" id="berlaku-selalu" name="berlaku" @checked($berlaku === 'selalu') wire:click="pilihBerlaku('selalu')">
                <label class="form-check-label" for="berlaku-selalu">{{ __('Selalu (semua dokumen jenis ini)') }}</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" id="berlaku-bila" name="berlaku" @checked($berlaku === 'bila') wire:click="pilihBerlaku('bila')">
                <label class="form-check-label" for="berlaku-bila">{{ __('Hanya bila…') }}</label>
            </div>
        </div>
        @if ($berlaku === 'bila')
            <div class="row g-3">
                @include('livewire.approval.partials.rule-form-kondisi')
            </div>
            <div class="form-text">{{ __('Semua isian yang diisi harus cocok. Aturan dengan syarat diperiksa sebelum aturan umum.') }}</div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <label class="form-label" for="aturan-nama-sederhana">{{ __('Nama aturan') }} <span class="wajib">*</span></label>
        <input class="form-control @error('form.name') is-invalid @enderror" id="aturan-nama-sederhana" type="text" wire:model.live.blur="form.name" maxlength="100">
        <div class="form-text">{{ $namaManual ? __('Nama diketik sendiri.') : __('Terisi otomatis dari pilihan di atas; boleh diubah.') }}</div>
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
