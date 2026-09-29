{{-- Tab Akun portal pada halaman detail klien (A-327): hanya-lihat, diubah dari layar Pengguna. --}}
<div class="card">
    <div class="card-header">
        <strong>{{ __('Akun portal klien ini') }}</strong>
        <span class="text-muted small ms-2">
            {{ __('Dikelola di Administrasi › Pengguna. Proyek yang boleh dilihat diatur lewat Tim site.') }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Peran') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Dari PIC') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data as $akun)
                    @php($status = $akun->status())
                    <tr wire:key="akun-{{ $akun->id }}">
                        <td>
                            @can('view', $akun)
                                <a href="{{ route('users.show', $akun->id) }}">{{ $akun->name }}</a>
                            @else
                                {{ $akun->name }}
                            @endcan
                        </td>
                        <td class="small text-muted">{{ $akun->email }}</td>
                        <td class="small">
                            {{ $akun->roleAssignments->pluck('role.name')->filter()->unique()->implode(', ') ?: '—' }}
                        </td>
                        <td><span class="badge text-bg-{{ $status->badge() }}">{{ $status->label() }}</span></td>
                        <td>{{ $akun->dari_pic ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            {{ __('Klien ini belum punya akun portal. Buat dari tab PIC.') }}
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
