{{--
    Panel "Riwayat approval" di halaman detail dokumen (20-approval §6.6).
    $riwayatApproval: koleksi ApprovalSnapshot dari ApprovalHistory::for().
--}}
<div class="card mb-3">
    <div class="card-header"><strong>{{ __('Riwayat approval') }}</strong></div>
    @forelse ($riwayatApproval as $s)
        <div class="card-body border-bottom small">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <span class="badge {{ $s->status->badge() }}">{{ $s->status->label() }}</span>
                    <strong>{{ $s->wasAutoApproved() ? __('Disetujui otomatis — tidak ada aturan (A-08)') : ($s->rule_name ?? __('Lapis minimum')) }}</strong>
                </div>
                <div class="text-muted">
                    {{ __('Diajukan') }} {{ $s->submitter?->name ?? __('Sistem') }} · {{ $s->submitted_at?->lokal()->format('d/m/Y H:i') }}
                    @if ($s->decided_at) · {{ __('Selesai') }} {{ $s->decided_at->lokal()->format('d/m/Y H:i') }} @endif
                </div>
            </div>
            @foreach ($s->steps ?? [] as $l)
                <div class="mb-2">
                    <div>
                        <strong>{{ __('Lapis') }} {{ $l['step_no'] }}</strong> — {{ $l['approver_label'] }}
                        <span class="text-muted">({{ \App\Domain\Approval\Enums\DecisionMode::from($l['decision_mode'])->label() }})</span>
                        @if ((int) $s->current_step === (int) $l['step_no'] && $s->status->value === 'pending')
                            <span class="badge text-bg-warning">{{ __('Berjalan') }}</span>
                        @endif
                    </div>
                    @foreach ($l['notes'] ?? [] as $n)
                        <div class="{{ ($l['escalated_to_admin'] ?? false) ? 'text-warning' : 'text-muted' }}">{{ $n }}</div>
                    @endforeach
                    <ul class="list-unstyled ms-3 mb-0">
                        @foreach ($s->tasks->where('step_no', $l['step_no']) as $t)
                            <li>
                                {{ $t->approver?->name }}
                                @if ($t->delegatedFrom) <span class="text-muted">({{ __('delegasi dari') }} {{ $t->delegatedFrom->name }})</span> @endif
                                <span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span>
                                @if ($t->status->value === 'open' && $t->due_at)
                                    <span class="text-muted">{{ __('batas') }} {{ $t->due_at->lokal()->format('d/m/Y H:i') }}</span>
                                    @if ($t->isOverdue()) <span class="badge text-bg-danger">{{ __('Lewat batas') }}</span> @endif
                                @endif
                                @foreach ($t->decisions as $d)
                                    <div class="text-muted">
                                        <span class="badge {{ $d->decision->badge() }}">{{ $d->decision->label() }}</span>
                                        {{ $d->decided_at?->lokal()->format('d/m/Y H:i') }} · {{ $d->decider?->name ?? __('Sistem') }}
                                        @if ($d->reason) · {{ $d->reason->label }} @endif
                                        @if ($d->comment) · {{ $d->comment }} @endif
                                    </div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card-body small text-muted">{{ __('Belum pernah diajukan ke approval.') }}</div>
    @endforelse
</div>
