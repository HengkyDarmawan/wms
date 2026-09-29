<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Aturan approval') }}</h1>
            <p class="text-muted mb-0">{{ __('Per jenis dokumen, aturan aktif diperiksa urut prioritas; aturan pertama yang kondisinya cocok dipakai. Tanpa aturan yang cocok, dokumen langsung disetujui.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('approval.rules.map') }}"><i class="bi bi-diagram-3"></i> {{ __('Peta approval') }}</a>
            @can('create', App\Domain\Approval\Models\ApprovalRule::class)
                <button class="btn btn-outline-primary" type="button" wire:click="bukaAturanDasar">{{ __('Pasang aturan dasar') }}</button>
            @endcan
            @can('approval.simulate')
                <a class="btn btn-outline-secondary" href="{{ route('approval.simulation') }}">{{ __('Simulasi') }}</a>
            @endcan
            @can('create', App\Domain\Approval\Models\ApprovalRule::class)
                <a class="btn btn-primary" href="{{ route('approval.rules.create') }}">{{ __('Aturan baru') }}</a>
            @endcan
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }} <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span></div>
    @endif

    @if ($dialogDasar)
        {{-- A-347: pratinjau sebelum memasang; tidak ada yang ditimpa. --}}
        <div class="card border-primary mb-3" role="dialog" aria-labelledby="judul-dasar">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong id="judul-dasar">{{ __('Pasang aturan dasar') }}</strong>
                <button class="btn-close" type="button" wire:click="$set('dialogDasar', false)" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="card-body">
                <p class="mb-2">{{ __('Sistem membuat satu aturan umum untuk setiap jenis dokumen yang belum punya aturan. Aturan yang sudah ada tidak diubah. Batas waktu 24 jam, lalu naik ke atasan si approver.') }}</p>
                <ul class="list-unstyled mb-3">
                    @foreach ($rencanaDasar as $r)
                        <li class="mb-1">
                            @if ($r['status'] === 'pasang')
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <strong>{{ $r['jenis']->label() }}</strong> → {{ $r['approver'] }}
                                @if ($r['peringatan'])
                                    <div class="small text-warning-emphasis ms-4"><i class="bi bi-exclamation-triangle"></i> {{ __($r['peringatan']) }}</div>
                                @endif
                            @elseif ($r['status'] === 'ada_aturan')
                                <i class="bi bi-dash-circle text-muted"></i>
                                <span class="text-muted">{{ $r['jenis']->label() }} — {{ __('dilewati: sudah punya aturan') }}</span>
                            @else
                                <i class="bi bi-shield-check text-muted"></i>
                                <span class="text-muted">{{ $r['jenis']->label() }} — {{ __('dilewati: sudah dijaga lapis minimum sistem') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @php $jumlahPasang = collect($rencanaDasar)->where('status', 'pasang')->count(); @endphp
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="button" wire:click="pasangAturanDasar" @disabled($jumlahPasang === 0)>
                        {{ $jumlahPasang === 0 ? __('Tidak ada yang perlu dipasang') : __('Pasang :n aturan', ['n' => $jumlahPasang]) }}
                    </button>
                    <button class="btn btn-outline-secondary" type="button" wire:click="$set('dialogDasar', false)">{{ __('Batal') }}</button>
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label" for="filter-jenis">{{ __('Jenis dokumen') }}</label>
                <select class="form-select" id="filter-jenis" wire:model.live="typeFilter">
                    <option value="">{{ __('Semua') }}</option>
                    @foreach ($types as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="filter-status-aturan">{{ __('Status') }}</label>
                <select class="form-select" id="filter-status-aturan" wire:model.live="statusFilter">
                    <option value="">{{ __('Semua') }}</option>
                    <option value="active">{{ __('Aktif') }}</option>
                    <option value="inactive">{{ __('Nonaktif') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Jenis') }}</th>
                        <th class="text-end" scope="col">{{ __('Prioritas') }}</th>
                        <th scope="col">{{ __('Nama') }}</th>
                        <th scope="col">{{ __('Kondisi') }}</th>
                        <th scope="col">{{ __('Lapis') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $r)
                        @php $kondisi = collect($r->conditions ?? [])->except('match'); @endphp
                        <tr wire:key="aturan-{{ $r->id }}">
                            <td><span class="badge text-bg-light">{{ $r->document_type->code() }}</span></td>
                            <td class="text-end">{{ $r->priority }}</td>
                            <td>
                                {{ $r->name }}
                                @if ($r->is_basic) <span class="badge text-bg-info">{{ __('Dasar') }}</span> @endif
                                <div class="small">{{ $kalimat->aturan($r) }}</div>
                                <div class="small text-muted">{{ trans_choice(':n dokumen memakai|:n dokumen memakai', $r->snapshots_count, ['n' => $r->snapshots_count]) }}</div>
                            </td>
                            <td class="small">
                                @if ($kondisi->isEmpty())
                                    <span class="text-muted">{{ __('Semua dokumen (lainnya)') }}</span>
                                @else
                                    {{ ($r->conditions['match'] ?? 'all') === 'any' ? __('Salah satu:') : __('Semua:') }}
                                    {{ $kondisi->keys()->map(fn ($k) => \App\Domain\Approval\Support\ConditionMatcher::KEYS[$k] ?? $k)->implode(', ') }}
                                @endif
                            </td>
                            <td class="small">
                                @foreach ($r->steps as $s)
                                    <div>{{ $s->step_no }}. {{ $resolver->label($s->approver_type, $s->approver_ref_id, $s->manager_levels) }}@if ($s->same_org_unit && $s->approver_type->limitableToOrgUnit()) <span class="badge text-bg-light">{{ __('divisi pemohon') }}</span>@endif <span class="text-muted">({{ $s->decision_mode->label() }}, {{ $s->timeout_hours }} {{ __('jam') }})</span></div>
                                @endforeach
                            </td>
                            <td>
                                <span class="badge {{ $r->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $r->is_active ? __('Aktif') : __('Nonaktif') }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                @can('update', $r)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('approval.rules.edit', $r) }}">{{ __('Ubah') }}</a>
                                    @if ($r->is_active)
                                        <button class="btn btn-sm btn-outline-danger" type="button" wire:click="setAktif({{ $r->id }}, false)">{{ __('Nonaktifkan') }}</button>
                                    @else
                                        <button class="btn btn-sm btn-outline-success" type="button" wire:click="setAktif({{ $r->id }}, true)">{{ __('Aktifkan') }}</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="7">{{ __('Belum ada aturan approval. Semua dokumen disetujui otomatis saat diajukan.') }}</td> {{-- A-08 --}}
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
