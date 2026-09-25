<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Tugas approval saya') }}</h1>
            <p class="text-muted mb-0">{{ __('Dokumen yang menunggu keputusan Anda, termasuk hasil delegasi dan eskalasi. Keputusan pertama yang tercatat berlaku.') }}</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link {{ $tab === 'saya' ? 'active' : '' }}" type="button" wire:click="pilihTab('saya')">{{ __('Menunggu saya') }}</button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $tab === 'riwayat' ? 'active' : '' }}" type="button" wire:click="pilihTab('riwayat')">{{ __('Sudah saya putus') }}</button>
        </li>
        @if ($bolehEskalasi)
            <li class="nav-item">
                <button class="nav-link {{ $tab === 'semua' ? 'active' : '' }}" type="button" wire:click="pilihTab('semua')">{{ __('Semua tugas terbuka') }}</button>
            </li>
        @endif
    </ul>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif
        </div>
    @endif

    @if ($taskId !== null)
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Tolak dokumen') }}</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="tolak-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                    <select class="form-select @error('form.reason') is-invalid @enderror" id="tolak-alasan" wire:model="form.reason">
                        <option value="">{{ __('Pilih alasan…') }}</option>
                        @foreach ($alasan as $kode => $label)
                            <option value="{{ $kode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tolak-ket">{{ __('Keterangan') }}</label>
                    <input class="form-control" id="tolak-ket" type="text" wire:model="form.notes" placeholder="{{ __('Opsional') }}">
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-danger" type="button" wire:click="tolak">{{ __('Tolak') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Dokumen') }}</th>
                        <th scope="col">{{ __('Aturan & lapis') }}</th>
                        <th scope="col">{{ __('Diajukan') }}</th>
                        <th scope="col">{{ $tab === 'semua' ? __('Approver') : __('Batas waktu') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $t)
                        @php
                            $s = $t->snapshot;
                            $jenis = $s->document_type;
                            $h = $registry->has($jenis) ? $registry->handler($jenis) : null;
                            $doc = $h?->find($s->document_id);
                            $ctx = $s->context ?? [];
                            $lapis = $s->step($t->step_no);
                        @endphp
                        <tr wire:key="tugas-{{ $t->id }}">
                            <td>
                                <span class="badge text-bg-light">{{ $jenis->code() }}</span>
                                @if ($doc !== null && $h->url($doc) !== null && auth()->user()->can('view', $doc))
                                    <a href="{{ $h->url($doc) }}">{{ $s->document_number }}</a>
                                @else
                                    {{ $s->document_number }}
                                @endif
                                <div class="small text-muted">{{ $jenis->label() }} · {{ $ctx['line_count'] ?? 0 }} {{ __('baris') }}</div>
                            </td>
                            <td>
                                {{ $s->rule_name ?? __('Lapis minimum') }}
                                <div class="small text-muted">
                                    {{ __('Lapis') }} {{ $t->step_no }}/{{ count($s->steps ?? []) }}
                                    @if ($lapis) · {{ \App\Domain\Approval\Enums\DecisionMode::from($lapis['decision_mode'])->label() }} @endif
                                </div>
                            </td>
                            <td>
                                {{ $s->submitter?->name ?? __('Sistem') }}
                                <div class="small text-muted">{{ $s->submitted_at?->lokal()->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>
                                @if ($tab === 'semua')
                                    {{ $t->approver?->name }}
                                @endif
                                <div class="{{ $tab === 'semua' ? 'small text-muted' : '' }}">
                                    {{ $t->due_at?->lokal()->format('d/m/Y H:i') }}
                                    @if ($t->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat batas') }}</span> @endif
                                </div>
                                @if ($t->delegatedFrom) <div class="small text-muted">{{ __('Delegasi dari') }} {{ $t->delegatedFrom->name }}</div> @endif
                                @if ($t->escalated_from_task_id) <div class="small text-muted">{{ __('Hasil eskalasi') }}</div> @endif
                            </td>
                            <td>
                                <span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span>
                                @foreach ($t->decisions as $d)
                                    <div class="small text-muted">{{ $d->decision->label() }}@if ($d->reason) · {{ $d->reason->label }}@endif</div>
                                @endforeach
                            </td>
                            <td class="text-end text-nowrap">
                                @can('decide', $t)
                                    <button class="btn btn-sm btn-primary" type="button" wire:click="setujui({{ $t->id }})">{{ __('Setujui') }}</button>
                                    <button class="btn btn-sm btn-outline-danger" type="button" wire:click="mintaTolak({{ $t->id }})">{{ __('Tolak') }}</button>
                                @endcan
                                @if ($tab === 'semua')
                                    @can('escalate', $t)
                                        <button class="btn btn-sm btn-outline-warning" type="button" wire:click="eskalasi({{ $t->id }})">{{ __('Eskalasi') }}</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">
                                {{ $tab === 'riwayat' ? __('Belum ada keputusan.') : __('Tidak ada tugas approval yang menunggu.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
