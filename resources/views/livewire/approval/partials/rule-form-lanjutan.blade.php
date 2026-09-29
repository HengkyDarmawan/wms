{{-- Mode lanjutan (A-348): semua isian aturan, sama dengan form lama. --}}
    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Aturan') }}</strong></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="aturan-jenis">{{ __('Jenis dokumen') }} <span class="wajib">*</span></label>
                <select class="form-select @error('form.document_type') is-invalid @enderror" id="aturan-jenis" wire:model.live="form.document_type" @disabled($ruleId)>
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.document_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label" for="aturan-nama">{{ __('Nama aturan') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.name') is-invalid @enderror" id="aturan-nama" type="text" wire:model.live.blur="form.name" maxlength="100">
                @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aturan-prioritas">{{ __('Prioritas') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.priority') is-invalid @enderror" id="aturan-prioritas" type="number" min="1" max="9999" wire:model.live.blur="form.priority">
                @error('form.priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" id="aturan-aktif" type="checkbox" wire:model="form.is_active">
                    <label class="form-check-label" for="aturan-aktif">{{ __('Aktif') }}</label>
                </div>
            </div>
            <div class="col-12 small text-muted">{{ __('Prioritas kecil diperiksa lebih dulu; aturan pertama yang cocok dipakai.') }}
                @unless ($prioritasManual) {{ __('Saat ini otomatis (:n): makin banyak kondisi, makin dulu diperiksa. Mengetik angka membuatnya tetap.', ['n' => $prioritasOtomatis]) }} @endunless</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Kondisi berlaku') }}</strong> <span class="text-muted small">{{ __('— kosongkan semua agar berlaku untuk semua dokumen jenis ini') }}</span></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="kondisi-cocok">{{ __('Cara menggabungkan') }}</label>
                <select class="form-select" id="kondisi-cocok" wire:model.live="conditions.match">
                    @foreach ($matches as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @include('livewire.approval.partials.rule-form-kondisi')
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ __('Lapis approval') }} <span class="wajib">*</span></strong>
            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahLapis">{{ __('Tambah lapis') }}</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">{{ __('Approver') }} <span class="wajib">*</span></th>
                        <th scope="col">{{ __('Cara putus') }} <span class="wajib">*</span></th>
                        <th scope="col">{{ __('Batas waktu (jam)') }} <span class="wajib">*</span> · {{ __('Kanal') }}</th>
                        <th scope="col">{{ __('Approver cadangan') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($steps as $i => $s)
                        <tr wire:key="lapis-{{ $i }}">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <select class="form-select form-select-sm mb-1" wire:model.live="steps.{{ $i }}.approver_type" aria-label="{{ __('Jenis approver') }}">
                                    @foreach ($approverTypes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                                </select>
                                @include('livewire.approval.partials.approver-ref', ['jenis' => $s['approver_type'] ?? '', 'model' => 'steps.'.$i.'.approver_ref_id'])
                                @if (($s['approver_type'] ?? '') === 'direct_manager')
                                    {{-- A-346: atasan 1 atau 2 tingkat di atas pemohon (peta jabatan). --}}
                                    <select class="form-select form-select-sm" wire:model.live="steps.{{ $i }}.manager_levels" aria-label="{{ __('Tingkat atasan') }}">
                                        <option value="1">{{ __('1 tingkat') }}</option>
                                        <option value="2">{{ __('2 tingkat') }}</option>
                                    </select>
                                @endif
                                @if (in_array($s['approver_type'] ?? '', ['role', 'position'], true))
                                    {{-- A-269: approver hanya dari divisi pemohon atau divisi induknya. --}}
                                    <div class="form-check small mt-1">
                                        <input class="form-check-input" id="lapis-divisi-{{ $i }}" type="checkbox" wire:model="steps.{{ $i }}.same_org_unit">
                                        <label class="form-check-label" for="lapis-divisi-{{ $i }}">{{ __('Hanya dari divisi pemohon') }}</label>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="steps.{{ $i }}.decision_mode" aria-label="{{ __('Cara putus') }}">
                                    @foreach ($modes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                                </select>
                            </td>
                            <td style="max-width: 7rem">
                                <input class="form-control form-control-sm" type="number" min="1" max="720" wire:model.live.blur="steps.{{ $i }}.timeout_hours" aria-label="{{ __('Batas waktu') }}">
                                {{-- Fase 2a (A-277, BR-APR-10): tombol Setujui/Tolak lewat WhatsApp. --}}
                                <select class="form-select form-select-sm mt-1" wire:model="steps.{{ $i }}.channel" aria-label="{{ __('Kanal lapis') }}" title="{{ $waAktif ? '' : __('WhatsApp belum aktif untuk company ini; lapis tetap lewat web') }}">
                                    <option value="web">{{ __('Web') }}</option>
                                    <option value="both">{{ __('Web & WhatsApp') }}</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm mb-1" wire:model.live="steps.{{ $i }}.backup_approver_type" aria-label="{{ __('Jenis approver cadangan') }}">
                                    <option value="">{{ __('Tanpa cadangan') }}</option>
                                    @foreach ($approverTypes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                                </select>
                                @include('livewire.approval.partials.approver-ref', ['jenis' => $s['backup_approver_type'] ?? '', 'model' => 'steps.'.$i.'.backup_ref_id'])
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($i > 0)
                                    <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="naikkanLapis({{ $i }})" title="{{ __('Naikkan') }}"><i class="bi bi-arrow-up"></i></button>
                                @endif
                                <button class="btn btn-sm btn-outline-danger" type="button" wire:click="hapusLapis({{ $i }})" title="{{ __('Hapus lapis') }}"><i class="bi bi-x-lg"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">{{ __('Pengaju tidak pernah menyetujui dokumennya sendiri; bila lapis menunjuk pengaju, lapis dialihkan ke atasannya. Approver nonaktif dialihkan ke cadangan, atasan, lalu Admin Company. Kanal WhatsApp tersedia di Fase 2a.') }}</div> {{-- BR-APR-03, BR-APR-06 --}}
    </div>
