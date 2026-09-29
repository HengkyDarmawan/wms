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

    {{-- A-348: mode sederhana bawaan; mode lanjutan memuat semua isian lama. --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" role="group" aria-label="{{ __('Mode form') }}">
        <div class="btn-group">
            <button type="button" class="btn btn-sm {{ $mode === 'sederhana' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="keMode('sederhana')" aria-pressed="{{ $mode === 'sederhana' ? 'true' : 'false' }}">{{ __('Mode sederhana') }}</button>
            <button type="button" class="btn btn-sm {{ $mode === 'lanjutan' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="keMode('lanjutan')" aria-pressed="{{ $mode === 'lanjutan' ? 'true' : 'false' }}">{{ __('Mode lanjutan') }}</button>
        </div>
        @if ($mode === 'sederhana' && $butuhLanjutan)
            <span class="small text-warning-emphasis"><i class="bi bi-info-circle"></i> {{ __('Aturan ini memakai pengaturan lanjutan (cara putus, batas waktu, kanal, cadangan, atau jenis approver lain) yang tetap tersimpan; ubah di Mode lanjutan.') }}</span>
        @endif
    </div>

    @if ($mode === 'sederhana')
        @include('livewire.approval.partials.rule-form-sederhana')
    @else
        @include('livewire.approval.partials.rule-form-lanjutan')
    @endif

    {{-- Kalimat ringkasan (A-348) — sama dengan yang tampil di daftar aturan dan Peta approval. --}}
    @if ($ringkasan !== '')
        <div class="alert alert-light border mb-3" role="status" aria-live="polite">
            <div class="small text-muted mb-1">{{ __('Ringkasan') }}</div>
            <div>{{ $ringkasan }}</div>
            <div class="small text-muted mt-1">
                @if (($mode === 'sederhana' && $berlaku === 'selalu') || $prioritasOtomatis === \App\Domain\Approval\Support\ConditionMatcher::PRIORITAS_UMUM)
                    {{ __('Aturan umum: diperiksa setelah aturan yang punya syarat.') }}
                @else
                    {{ __('Punya syarat: diperiksa sebelum aturan umum.') }}
                @endif
            </div>
        </div>
    @endif

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
