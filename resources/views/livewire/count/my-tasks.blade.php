<div class="mx-auto" style="max-width: 40rem">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('Hitungan saya') }}</h1>
            <p class="text-muted small mb-0">{{ __('Hitung buta: isi jumlah fisik yang Anda lihat di bin.') }}</p>
        </div>
    </div>

    @forelse ($terbuka as $a)
        <a class="card mb-2 text-decoration-none" href="{{ route('count-tasks.show', $a) }}" wire:key="terbuka-{{ $a->id }}">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="h5 mb-1 text-body">{{ $a->bin?->code }} @if ($a->bin?->count_flag) <span class="badge text-bg-warning">⚑</span> @endif</div>
                    <div class="small text-muted">{{ $a->stockCount?->number }} · {{ $a->round === 2 ? __('Hitung ulang') : __('Hitungan pertama') }}</div>
                </div>
                <span class="btn btn-primary">{{ __('Hitung') }}</span>
            </div>
        </a>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-4">{{ __('Tidak ada bin yang menunggu dihitung.') }}</div></div>
    @endforelse

    @if ($selesai->isNotEmpty())
        <h2 class="h6 text-muted mt-4">{{ __('Sudah selesai') }}</h2>
        <ul class="list-group">
            @foreach ($selesai as $a)
                <li class="list-group-item d-flex justify-content-between small" wire:key="selesai-{{ $a->id }}">
                    <span>{{ $a->bin?->code }} · {{ $a->stockCount?->number }}</span>
                    <span class="badge text-bg-success">{{ __('Selesai') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
