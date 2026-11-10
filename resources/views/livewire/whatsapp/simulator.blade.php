<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Kotak pesan WhatsApp (simulasi)') }}</h1>
            <p class="text-muted mb-0">{{ __('Mode demo tanpa akun Meta: pesan tidak benar-benar terkirim. Tekan tombol di pesan untuk meniru penerima menjawab dari HP.') }}</p>
        </div>
        <a wire:navigate class="btn btn-outline-secondary" href="{{ route('settings.company') }}">{{ __('Pengaturan company') }}</a>
    </div>

    @if ($hasil !== '')
        <div class="alert alert-info py-2" role="status">{{ $hasil }}</div>
    @endif

    <div class="card">
        <div class="card-body border-bottom">
            <label class="form-label" for="sim-nomor">{{ __('Penerima') }}</label>
            <select class="form-select" id="sim-nomor" wire:model.live="nomor" style="max-width: 24rem">
                <option value="">{{ __('Semua nomor') }}</option>
                @foreach ($nomorList as $n)
                    <option value="{{ $n }}">{{ ($nama[$n] ?? __('Tanpa nama')).' · '.$n }}</option>
                @endforeach
            </select>
        </div>
        <div class="card-body d-flex flex-column gap-2" style="background: var(--nx-surface-1)">
            @forelse ($pesan as $m)
                @php($isi = $m->payload['pesan'] ?? null)
                @php($keluar = $m->direction === 'out')
                <div class="d-flex {{ $keluar ? 'justify-content-start' : 'justify-content-end' }}" wire:key="wa-{{ $m->id }}">
                    <div class="border rounded-3 p-2 shadow-sm" style="max-width: 34rem; background: {{ $keluar ? 'var(--nx-card-bg)' : 'var(--nx-primary-subtle)' }}">
                        <div class="small text-muted mb-1">
                            {{ $keluar ? __('Ke :n', ['n' => ($nama[$m->to_number] ?? $m->to_number)]) : __('Dari :n', ['n' => ($nama[$m->to_number] ?? $m->to_number)]) }}
                            · {{ $m->created_at?->lokal()->format('d/m H:i') }}
                            @if ($m->template) · <code>{{ $m->template }}</code> @endif
                            @if ($m->status === 'failed') · <span class="text-danger">{{ __('gagal') }}</span> @endif
                        </div>
                        @if (! $keluar)
                            <div>{{ __('Menekan tombol') }}: <code>{{ $m->payload['payload'] ?? '-' }}</code></div>
                        @elseif (($isi['type'] ?? '') === 'text')
                            <div style="white-space: pre-line">{{ $isi['text']['body'] ?? '' }}</div>
                        @elseif ($isi)
                            @php($komponen = collect($isi['template']['components'] ?? []))
                            @foreach ($komponen->where('type', 'body') as $b)
                                @foreach ($b['parameters'] ?? [] as $p)
                                    <div>{{ $p['text'] ?? '' }}</div>
                                @endforeach
                            @endforeach
                            @php($tombol = $komponen->where('type', 'button'))
                            @if ($tombol->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach ($tombol as $t)
                                        @if (($t['sub_type'] ?? '') === 'quick_reply')
                                            @php($pl = (string) ($t['parameters'][0]['payload'] ?? ''))
                                            <button class="btn btn-sm {{ str_ends_with($pl, '|R') ? 'btn-outline-danger' : 'btn-outline-success' }}" type="button" wire:click="tekan({{ $m->id }}, {{ (int) $t['index'] }})">
                                                {{ str_ends_with($pl, '|R') ? __('Tolak') : (str_ends_with($pl, '|A') ? __('Setujui') : __('Tombol :i', ['i' => (int) $t['index'] + 1])) }}
                                            </button>
                                        @else
                                            <span class="btn btn-sm btn-outline-secondary disabled">{{ __('Buka tautan') }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="text-muted small">{{ __('Isi pesan tidak tersimpan (dikirim sebelum mode simulasi).') }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-muted small">{{ __('Belum ada pesan. Pesan muncul di sini saat ada notifikasi atau approval yang dikirim lewat WhatsApp.') }}</div>
            @endforelse
        </div>
    </div>
</div>
