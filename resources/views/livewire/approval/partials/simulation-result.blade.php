{{-- Hasil simulasi approval (BR-APR-11). $hasil dari SimulateApproval::run(). --}}
@if (! empty($hasil['document']))
    <p class="mb-2">{{ __('Dokumen contoh') }}: <strong>{{ $hasil['document'] }}</strong></p>
@endif

@if ($hasil['mode'] === 'draft')
    <p class="mb-2">
        @if ($hasil['matched'])
            <span class="badge text-bg-success">{{ __('Kondisi cocok') }}</span> {{ __('Draf aturan ini akan berlaku untuk dokumen tersebut.') }}
        @else
            <span class="badge text-bg-secondary">{{ __('Kondisi tidak cocok') }}</span> {{ __('Draf aturan ini tidak berlaku untuk dokumen tersebut.') }}
        @endif
    </p>
    @if ($hasil['checks'] !== [])
        <ul class="small mb-2">
            @foreach ($hasil['checks'] as $c)
                <li>{{ $c['label'] }}: {!! $c['ok'] ? '<span class="text-success">'.e(__('cocok')).'</span>' : '<span class="text-danger">'.e(__('tidak cocok')).'</span>' !!}</li>
            @endforeach
        </ul>
    @endif
@else
    @if ($hasil['evaluations'] !== [])
        <div class="table-responsive mb-3"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Prioritas') }}</th><th>{{ __('Aturan') }}</th><th>{{ __('Hasil') }}</th></tr></thead>
            <tbody>
                @foreach ($hasil['evaluations'] as $e)
                    <tr>
                        <td>{{ $e['priority'] }}</td>
                        <td>{{ $e['name'] }}</td>
                        <td>
                            @if ($e['chosen']) <span class="badge text-bg-success">{{ __('Dipakai') }}</span>
                            @elseif ($e['matched']) <span class="badge text-bg-info">{{ __('Cocok, kalah prioritas') }}</span>
                            @else <span class="badge text-bg-secondary">{{ __('Tidak cocok') }}</span> @endif
                            <span class="small text-muted">
                                @foreach ($e['checks'] as $c) {{ $c['label'] }}: {{ $c['ok'] ? '✓' : '✗' }}@if (! $loop->last), @endif @endforeach
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
    @if ($hasil['auto_approved'])
        <div class="alert alert-info mb-0">{{ __('Tidak ada aturan yang cocok: dokumen akan langsung disetujui saat diajukan.') }}</div> {{-- A-08 --}}
    @endif
@endif

@if ($hasil['layers'] !== [])
    <ol class="mb-0">
        @foreach ($hasil['layers'] as $l)
            <li class="mb-1">
                <strong>{{ $l['approver_label'] }}</strong>
                <span class="text-muted small">({{ \App\Domain\Approval\Enums\DecisionMode::from($l['decision_mode'])->label() }}, {{ $l['timeout_hours'] }} {{ __('jam') }})</span>:
                @if ($l['blocked'])
                    <span class="badge text-bg-danger">{{ __('Tidak ada approver — dokumen tidak bisa diajukan') }}</span>
                @else
                    {{ implode(', ', $l['approver_names']) }}
                @endif
                @foreach ($l['notes'] as $n)
                    <div class="small {{ $l['escalated_to_admin'] ? 'text-warning' : 'text-muted' }}">{{ $n }}</div>
                @endforeach
            </li>
        @endforeach
    </ol>
@endif
