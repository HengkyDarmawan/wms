<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $ruleId ? __('Ubah aturan approval') : __('Aturan approval baru') }}</h1>
            <p class="text-muted mb-0">{{ __('Kondisi dokumen gudang tidak memakai nilai uang; hanya Purchase Order yang boleh memakai nilai PO. Dokumen yang sedang menunggu tidak terpengaruh perubahan aturan.') }}</p> {{-- BR-APR-07, D-28, BR-APR-01 --}}
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('approval.rules.index') }}">{{ __('Kembali') }}</a>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

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
                <input class="form-control @error('form.name') is-invalid @enderror" id="aturan-nama" type="text" wire:model="form.name" maxlength="100">
                @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aturan-prioritas">{{ __('Prioritas') }} <span class="wajib">*</span></label>
                <input class="form-control @error('form.priority') is-invalid @enderror" id="aturan-prioritas" type="number" min="1" max="9999" wire:model="form.priority">
                @error('form.priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" id="aturan-aktif" type="checkbox" wire:model="form.is_active">
                    <label class="form-check-label" for="aturan-aktif">{{ __('Aktif') }}</label>
                </div>
            </div>
            <div class="col-12 small text-muted">{{ __('Prioritas kecil diperiksa lebih dulu; aturan pertama yang cocok dipakai.') }}</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Kondisi berlaku') }}</strong> <span class="text-muted small">{{ __('— kosongkan semua agar berlaku untuk semua dokumen jenis ini') }}</span></div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="kondisi-cocok">{{ __('Cara menggabungkan') }}</label>
                <select class="form-select" id="kondisi-cocok" wire:model="conditions.match">
                    @foreach ($matches as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if (in_array('warehouse_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-gudang">{{ __('Gudang') }}</label>
                    <select class="form-select" id="kondisi-gudang" multiple size="4" wire:model="conditions.warehouse_ids">
                        @foreach ($warehouses as $w) <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option> @endforeach
                    </select>
                </div>
            @endif
            @if (in_array('project_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-proyek">{{ __('Proyek') }}</label>
                    <select class="form-select" id="kondisi-proyek" multiple size="4" wire:model="conditions.project_ids">
                        @foreach ($projects as $p) <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option> @endforeach
                    </select>
                </div>
            @endif
            @if (in_array('category_ids', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-kategori">{{ __('Kategori barang (termasuk sub-kategori)') }}</label>
                    <select class="form-select" id="kondisi-kategori" multiple size="4" wire:model="conditions.category_ids">
                        @foreach ($categories as $c) <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option> @endforeach
                    </select>
                </div>
            @endif
            @if (in_array('ownership_models', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-kepemilikan">{{ __('Model kepemilikan') }}</label>
                    <select class="form-select" id="kondisi-kepemilikan" multiple size="3" wire:model="conditions.ownership_models">
                        @foreach ($ownerships as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
            @endif
            @if (in_array('vendor_types', $allowed, true))
                <div class="col-md-4">
                    <label class="form-label" for="kondisi-vendor">{{ __('Jenis vendor') }}</label>
                    <select class="form-select" id="kondisi-vendor" multiple size="4" wire:model="conditions.vendor_types">
                        @foreach ($vendorTypes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
            @endif
            @if (in_array('line_count_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-baris">{{ __('Jumlah baris ≥') }}</label>
                    <input class="form-control" id="kondisi-baris" type="number" min="1" wire:model="conditions.line_count_min">
                </div>
            @endif
            @if (in_array('order_value_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-nilai">{{ __('Nilai PO ≥ (Rp)') }}</label>
                    <input class="form-control" id="kondisi-nilai" type="number" min="0" step="1000" wire:model="conditions.order_value_min">
                    <div class="form-text">{{ __('Hanya untuk Purchase Order.') }}</div> {{-- D-28 --}}
                </div>
            @endif
            @if (in_array('line_qty_min', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-qty">{{ __('Jumlah per baris ≥ (satuan dasar)') }}</label>
                    <input class="form-control" id="kondisi-qty" type="number" min="0" step="0.0001" wire:model="conditions.line_qty_min">
                </div>
            @endif
            @if (in_array('from_client', $allowed, true))
                <div class="col-md-3">
                    <label class="form-label" for="kondisi-klien">{{ __('Permintaan dari klien') }}</label>
                    <select class="form-select" id="kondisi-klien" wire:model="conditions.from_client">
                        <option value="">{{ __('Abaikan') }}</option>
                        <option value="1">{{ __('Ya') }}</option>
                        <option value="0">{{ __('Tidak') }}</option>
                    </select>
                </div>
            @endif
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
                        <th scope="col">{{ __('Batas waktu (jam)') }} <span class="wajib">*</span></th>
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
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="steps.{{ $i }}.decision_mode" aria-label="{{ __('Cara putus') }}">
                                    @foreach ($modes as $nilai => $label) <option value="{{ $nilai }}">{{ $label }}</option> @endforeach
                                </select>
                            </td>
                            <td style="max-width: 7rem">
                                <input class="form-control form-control-sm" type="number" min="1" max="720" wire:model="steps.{{ $i }}.timeout_hours" aria-label="{{ __('Batas waktu') }}">
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

    @can('approval.simulate')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Simulasi sebelum disimpan') }}</strong></div> {{-- BR-APR-11 --}}
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="sim-nomor">{{ __('Nomor dokumen contoh') }}</label>
                    <input class="form-control @error('sim.number') is-invalid @enderror" id="sim-nomor" type="text" wire:model="sampleNumber" placeholder="REQ/…">
                    @error('sim.number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-primary" type="button" wire:click="simulasikan">{{ __('Simulasikan') }}</button>
                </div>
            </div>
            @if ($simulasi !== null)
                <div class="card-body border-top">
                    @include('livewire.approval.partials.simulation-result', ['hasil' => $simulasi])
                </div>
            @endif
        </div>
    @endcan

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="button" wire:click="simpan">{{ __('Simpan aturan') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('approval.rules.index') }}">{{ __('Batal') }}</a>
    </div>
</div>
