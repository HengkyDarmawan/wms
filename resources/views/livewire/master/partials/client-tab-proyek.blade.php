{{-- Tab Proyek pada halaman detail klien (A-327). --}}
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <strong>{{ __('Proyek klien ini') }}</strong>
        @can('create', \App\Domain\Master\Models\Project::class)
            <a class="btn btn-sm btn-primary" href="{{ route('projects.index', ['klien' => $client->id]) }}">
                <i class="bi bi-plus-lg"></i> {{ __('Tambah proyek') }}
            </a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Kode') }}</th>
                    <th>{{ __('Nama proyek') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Gudang Site') }}</th>
                    <th>{{ __('PIC proyek (kita)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data as $proyek)
                    <tr wire:key="proyek-{{ $proyek->id }}">
                        <td class="text-muted small">{{ $proyek->code }}</td>
                        <td>
                            <a href="{{ route('projects.show', $proyek->id) }}">{{ $proyek->name }}</a>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $proyek->statusBadge() }}">{{ $proyek->status->label() }}</span>
                        </td>
                        <td>{{ $proyek->site_codes ?: '—' }}</td>
                        <td>{{ $proyek->pic?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            {{ __('Klien ini belum punya proyek.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($data->hasPages())
        <div class="card-footer">{{ $data->links() }}</div>
    @endif
</div>
