<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $task->number }}
                <span class="badge {{ $task->status->badge() }}">{{ $task->status->label() }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $task->warehouse?->code }} — {{ $task->warehouse?->name }}
                @if ($task->assignee)
                    · {{ __('Petugas') }}: {{ $task->assignee->name }}
                @endif
            </p>
            @if ($task->hasFreezeOverride())
                <p class="small text-warning-emphasis mb-0">
                    <i class="bi bi-unlock" aria-hidden="true"></i>
                    {{ __('Override bin beku') }} · {{ $task->freezeOverrider?->name }} · {{ $task->freeze_override_at->lokal()->format('d/m/Y H:i') }} — {{ $task->freeze_override_reason }}
                </p>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @include('print.partials.button', ['jenis' => \App\Domain\Template\Enums\DocumentTemplateType::PickTask, 'id' => $task->id, 'teks' => __('Cetak picklist')])
            <a class="btn btn-outline-secondary" href="{{ route('picks.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">
            {{ $ruleError }}
            @if ($ruleCode !== '')
                <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span>
            @endif
        </div>
    @endif

    @if ($dialog === 'batal')
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Batalkan tugas picking') }}</strong></div>
            <div class="card-body">
                <label class="form-label" for="pck-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                <select class="form-select @error('reasonCode') is-invalid @enderror" id="pck-alasan"
                        wire:model="reasonCode">
                    <option value="">{{ __('Pilih alasan…') }}</option>
                    @foreach ($alasan as $kode => $label)
                        <option value="{{ $kode }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="batalkan">{{ __('Batalkan') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($dialog === 'override')
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Override bin beku untuk SJ mendesak') }}</strong></div>
            <div class="card-body">
                <p class="small text-muted">{{ __('Picking boleh mengambil dari bin yang sedang dihitung opname. Angka sesi opname bin itu disesuaikan dengan jumlah yang diambil dan bin ditandai perlu dihitung ulang.') }}</p>
                <label class="form-label" for="pck-override">{{ __('Alasan') }} <span class="wajib">*</span></label>
                <input class="form-control @error('alasanOverride') is-invalid @enderror" id="pck-override" type="text" maxlength="255"
                       wire:model="alasanOverride" placeholder="{{ __('mis. SJ darurat pengecoran besok pagi') }}">
                @error('alasanOverride') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="override">{{ __('Izinkan picking') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="tutupDialog">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    @if ($task->status->value === 'in_progress')
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label" for="pck-pindai">{{ __('Pindai bin lalu item') }}</label>
                <input class="form-control @error('kodePindai') is-invalid @enderror" id="pck-pindai" type="text" data-scan
                       autocomplete="off" wire:model="kodePindai" wire:keydown.enter.prevent="pindai"
                       placeholder="{{ __('Kode bin, kode item, atau barcode item') }}">
                @error('kodePindai') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">
                    {{ __('Bin aktif') }}: {{ $binPindai ? ($bins->firstWhere('id', $binPindai)?->code ?? '—') : '—' }} ·
                    {{ __('Item yang dipindai dicatat sejumlah isian barisnya; ubah jumlah lalu Catat bila kurang.') }}
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><strong>{{ __('Baris alokasi') }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Item') }}</th>
                        <th scope="col">{{ __('Bin') }}</th>
                        <th class="text-end" scope="col">{{ __('Dialokasikan') }}</th>
                        <th scope="col" style="width: 12%">{{ __('Diambil') }}</th>
                        <th scope="col" style="width: 18%">{{ __('Alasan kurang') }}</th>
                        <th class="text-end" scope="col">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $l)
                        <tr wire:key="pck-baris-{{ $l->id }}"
                            @class(['table-warning' => $l->scanned_at !== null && $l->isShort(), 'table-info' => $sorot === $l->id && ! $l->isShort()])>
                            <td>
                                {{ $l->item?->code }}
                                <div class="small text-muted">{{ $l->item?->name }}</div>
                            </td>
                            <td>
                                @if ($task->status->value === 'in_progress')
                                    <select class="form-select form-select-sm"
                                            wire:model="isian.{{ $l->id }}.bin_id">
                                        @foreach ($bins as $bin)
                                            <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                                        @endforeach
                                    </select>
                                    @if ($l->binWasOverridden())
                                        <div class="small text-muted">
                                            {{ __('Saran') }}: {{ $l->suggestedBin?->code }}
                                        </div>
                                    @endif
                                    <input class="form-control form-control-sm mt-1" type="text"
                                           wire:model="isian.{{ $l->id }}.override_reason"
                                           placeholder="{{ __('Alasan bila ganti bin') }}">
                                @else
                                    {{ $l->bin?->code }}
                                    @if ($l->binWasOverridden())
                                        <div class="small text-muted">
                                            {{ __('Semula') }}: {{ $l->suggestedBin?->code }} ·
                                            {{ $l->override_reason }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $l->qty_allocated, 2, ',', '.') }}</td>
                            <td>
                                @if ($task->status->value === 'in_progress')
                                    <input class="form-control form-control-sm" type="number" step="0.0001" min="0"
                                           wire:model="isian.{{ $l->id }}.qty_picked">
                                @else
                                    {{ number_format((float) $l->qty_picked, 2, ',', '.') }}
                                @endif
                            </td>
                            <td>
                                @if ($task->status->value === 'in_progress')
                                    <select class="form-select form-select-sm"
                                            wire:model="isian.{{ $l->id }}.short_reason">
                                        <option value="">{{ __('— tidak kurang —') }}</option>
                                        @foreach ($alasan as $kode => $label)
                                            <option value="{{ $kode }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $l->shortReason?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($task->status->value === 'in_progress')
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="catat({{ $l->id }})">
                                        {{ __('Catat') }}
                                    </button>
                                @elseif ($l->scanned_at)
                                    <span class="badge text-bg-success">{{ __('Tercatat') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada baris.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap gap-2">
            @can('start', $task)
                <button class="btn btn-primary" type="button" wire:click="mulai">{{ __('Mulai picking') }}</button>
            @endcan

            @can('overrideFreeze', $task)
                <button class="btn btn-outline-warning" type="button" wire:click="mintaOverride">{{ __('Override bin beku') }}</button>
            @endcan

            @can('complete', $task)
                <button class="btn btn-success" type="button" wire:click="selesaikan">
                    {{ __('Selesaikan picking') }}
                </button>
            @endcan

            @can('cancel', $task)
                <button class="btn btn-outline-danger" type="button" wire:click="mintaBatal">
                    {{ __('Batalkan') }}
                </button>
            @endcan
        </div>
    </div>
    {{-- A-252: dokumen asal & turunan. --}}
    @unless ($portal ?? false)
        <x-related-documents :document="$task" />
    @endunless
</div>
