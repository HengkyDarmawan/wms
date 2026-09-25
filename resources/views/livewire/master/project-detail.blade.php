@php($angka = fn ($n, $d = 2) => number_format((float) $n, $d, ',', '.'))
<div>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                {{ $project->name }}
                <span class="badge text-bg-{{ $project->statusBadge() }} ms-1">{{ $project->status->label() }}</span>
            </h1>
            <p class="text-muted mb-1">
                {{ $project->code }}
                @if ($project->is_internal)
                    · <span class="badge text-bg-info">{{ __('Proyek Internal') }}</span>
                @else
                    · {{ $project->client?->name ?? '—' }}
                @endif
                · {{ __('PIC') }} {{ $project->pic?->name ?? '—' }}
                · {{ $project->start_date?->format('d/m/Y') ?? '—' }} &rarr; {{ $project->target_end_date?->format('d/m/Y') ?? '—' }}
                @if ($project->closed_at) · {{ __('Ditutup') }} {{ $project->closed_at->lokal()->format('d/m/Y') }} @endif
            </p>
            <p class="small mb-0">
                {{ __('Gudang Site') }}:
                @forelse ($sites as $g)
                    <a class="badge text-bg-light text-decoration-none border" href="{{ route('warehouses.show', $g) }}">{{ $g->code }} — {{ $g->name }}</a>
                @empty
                    <span class="text-muted">{{ __('belum ada') }}</span>
                @endforelse
                @if ($project->address) · <span class="text-muted">{{ $project->address }}</span> @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $project)
                <a class="btn btn-outline-secondary" href="{{ route('projects.index', ['ubah' => $project->id]) }}">{{ __('Ubah') }}</a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('projects.index') }}">{{ __('Kembali') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    {{-- Aksi dari proyek: form tujuan langsung terisi proyek ini (A-228). --}}
    @if ($project->acceptsDocuments())
        <div class="d-flex flex-wrap gap-2 mb-3">
            @can('request.create')
                <a class="btn btn-primary" href="{{ route('requests.create', ['project' => $project->id]) }}"><i class="bi bi-plus-lg"></i> {{ __('Permintaan material') }}</a>
            @endcan
            @can('transfer.create')
                @if ($sites->isNotEmpty())
                    <a class="btn btn-outline-primary" href="{{ route('transfers.create', ['from_warehouse' => $sites->first()->id]) }}"><i class="bi bi-arrow-left-right"></i> {{ __('Transfer ke proyek lain') }}</a>
                @endif
            @endcan
            @can('issue.create')
                <a class="btn btn-outline-primary" href="{{ route('issues.create', ['project' => $project->id]) }}"><i class="bi bi-hammer"></i> {{ __('Pemakaian material') }}</a>
            @endcan
            @can('conversion.create')
                <a class="btn btn-outline-primary" href="{{ route('conversions.create', ['project' => $project->id]) }}"><i class="bi bi-scissors"></i> {{ __('Konversi') }}</a>
            @endcan
            @can('return.create')
                <a class="btn btn-outline-primary" href="{{ route('returns.create', ['project' => $project->id]) }}"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Retur') }}</a>
            @endcan
            @can('issue.view')
                <a class="btn btn-outline-secondary" href="{{ route('reports.show', ['report' => 'material-per-proyek', 'filters' => ['project_id' => $project->id]]) }}"><i class="bi bi-file-earmark-bar-graph"></i> {{ __('Laporan material') }}</a>
            @endcan
        </div>
    @endif

    @can('close', $project)
        @unless ($project->is_internal)
            @if (! $dialogStatus && $targetOptions !== [])
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-warning" type="button" wire:click="mintaUbahStatus">
                        {{ $project->status === \App\Domain\Master\Enums\ProjectStatus::Active ? __('Tutup / batalkan proyek') : __('Ubah status proyek') }}
                    </button>
                </div>
            @endif
        @endunless
    @endcan

    @if ($dialogStatus)
        <div class="card border-warning mb-3">
            <div class="card-header"><strong>{{ __('Ubah status proyek') }}</strong></div>
            <div class="card-body">
                @if ($project->status === \App\Domain\Master\Enums\ProjectStatus::Active)
                    <p class="small mb-2">{{ __('Checklist penutupan:') }}</p> {{-- BR-PRJ-02 --}}
                    @if ($blockers === [])
                        <p class="small text-success mb-3"><i class="bi bi-check-circle"></i> {{ __('Tidak ada dokumen terbuka, aset dipinjam, atau stok di Gudang Site. Proyek boleh ditutup.') }}</p>
                    @else
                        <ul class="small text-danger mb-3">
                            @foreach ($blockers as $b)
                                <li><i class="bi bi-x-circle"></i> {{ $b }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="prj-status-tujuan">{{ __('Status tujuan') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('targetStatus') is-invalid @enderror" id="prj-status-tujuan" wire:model="targetStatus">
                            <option value="">{{ __('Pilih status…') }}</option>
                            @foreach ($targetOptions as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('targetStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prj-alasan">{{ __('Alasan') }} <span class="wajib">*</span></label>
                        <select class="form-select @error('reasonCode') is-invalid @enderror" id="prj-alasan" wire:model="reasonCode">
                            <option value="">{{ __('Pilih alasan…') }}</option>
                            @foreach ($alasan as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reasonCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prj-keterangan">{{ __('Keterangan') }}</label>
                        <input class="form-control" id="prj-keterangan" type="text" wire:model="reasonNotes" placeholder="{{ __('Opsional') }}">
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-warning" type="button" wire:click="ubahStatus">{{ __('Simpan status') }}</button>
                <button class="btn btn-outline-secondary" type="button" wire:click="batalUbahStatus">{{ __('Tutup') }}</button>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => __('Stok on-site'), 'nilai' => $angka($ringkas['stok']), 'ikon' => 'bi-boxes', 'tab' => 'stok', 'hint' => __('satuan dasar, Tersedia di Gudang Site')],
            ['label' => __('Permintaan terbuka'), 'nilai' => $ringkas['req'], 'ikon' => 'bi-clipboard-check', 'tab' => 'permintaan', 'hint' => __('belum selesai/ditutup')],
            ['label' => __('Aset di proyek'), 'nilai' => $ringkas['aset'], 'ikon' => 'bi-truck-front', 'tab' => 'aset', 'hint' => __('sedang dipinjam')],
            ['label' => __('Menunggu approval'), 'nilai' => $ringkas['approval'], 'ikon' => 'bi-check2-square', 'tab' => 'approval', 'hint' => __('dokumen proyek ini')],
        ] as $k)
            <div class="col-6 col-xl-3">
                <button class="card h-100 w-100 text-start border-0 shadow-sm" type="button" wire:click="pilihTab('{{ $k['tab'] }}')">
                    <div class="card-body d-flex align-items-start gap-3">
                        <i class="bi {{ $k['ikon'] }} fs-3 text-primary" aria-hidden="true"></i>
                        <div>
                            <div class="fs-4 fw-semibold">{{ $k['nilai'] }}</div>
                            <div class="fw-semibold">{{ $k['label'] }}</div>
                            <div class="small text-muted">{{ $k['hint'] }}</div>
                        </div>
                    </div>
                </button>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $kunci => $label)
            <li class="nav-item">
                <button class="nav-link {{ $tab === $kunci ? 'active' : '' }}" type="button" wire:click="pilihTab('{{ $kunci }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    @if ($tab === 'permintaan')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Pemohon') }}</th><th>{{ __('Dibutuhkan') }}</th><th class="text-end">{{ __('Baris') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data as $r)
                            <tr wire:key="req-{{ $r->id }}">
                                <td><a href="{{ route('requests.show', $r) }}">{{ $r->number }}</a><div class="small text-muted">{{ $r->created_at?->lokal()->format('d/m/Y') }}</div></td>
                                <td>{{ $r->requester?->name }}</td>
                                <td>{{ $r->required_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="text-end">{{ $r->lines_count }}</td>
                                <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="5">{{ __('Belum ada permintaan untuk proyek ini.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data->hasPages()) <div class="card-footer">{{ $data->links() }}</div> @endif
        </div>
    @elseif ($tab === 'pengiriman')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Dari') }}</th><th>{{ __('Tujuan') }}</th><th>{{ __('Driver / pembawa') }}</th><th>{{ __('Berangkat') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data as $s)
                            <tr wire:key="sj-{{ $s->id }}">
                                <td><a href="{{ route('shipments.show', $s) }}">{{ $s->number }}</a></td>
                                <td>{{ $s->warehouse?->code }}</td>
                                <td>{{ $s->destinationWarehouse?->code ?? $s->destination_type?->label() }}</td>
                                <td>{{ $s->driver?->name ?? $s->carried_by_name ?? '—' }}</td>
                                <td>{{ $s->shipped_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
                                <td><span class="badge {{ $s->status->badge() }}">{{ $s->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada pengiriman ke proyek ini.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data->hasPages()) <div class="card-footer">{{ $data->links() }}</div> @endif
        </div>
    @elseif ($tab === 'stok')
        {{-- BR-PRJ-05: tiga sub-tampilan Stok On-site dari sumber yang sama dengan form retur. --}}
        @foreach (\App\Domain\Return\Enums\ReturnSource::cases() as $sumber)
            @php($baris = $data->get($sumber->value, collect()))
            @if ($baris->isNotEmpty() || in_array($sumber, [\App\Domain\Return\Enums\ReturnSource::SiteStock, \App\Domain\Return\Enums\ReturnSource::OnSiteAsset, \App\Domain\Return\Enums\ReturnSource::DeliveredToClient], true))
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ $sumber->label() }}</strong> <span class="small text-muted">{{ $baris->count() }} {{ __('baris') }}</span></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Lot / serial / potongan') }}</th><th>{{ $sumber === \App\Domain\Return\Enums\ReturnSource::DeliveredToClient ? __('Surat jalan') : __('Bin') }}</th><th class="text-end">{{ __('Jumlah') }}</th></tr></thead>
                            <tbody>
                                @forelse ($baris as $c)
                                    <tr wire:key="stok-{{ $c['key'] }}">
                                        <td>{{ $c['item_code'] }} <div class="small text-muted">{{ $c['item_name'] }}</div></td>
                                        <td class="small">{{ $c['tracking'] ?: '—' }}</td>
                                        <td class="small">{{ $c['shipment_number'] ?? $c['bin_code'] ?? '—' }}</td>
                                        <td class="text-end">{{ $angka($c['max'], 4) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted py-3" colspan="4">{{ __('Kosong.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    @elseif ($tab === 'pemakaian')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Gudang Site') }}</th><th>{{ __('Dicatat') }}</th><th class="text-end">{{ __('Baris') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data as $i)
                            <tr wire:key="isu-{{ $i->id }}">
                                <td><a href="{{ route('issues.show', $i) }}">{{ $i->number }}</a>@if ($i->reversal_of_id) <span class="badge text-bg-secondary">{{ __('pembalik') }}</span> @endif</td>
                                <td>{{ $i->warehouse?->code }}</td>
                                <td>{{ $i->issuer?->name }} <div class="small text-muted">{{ $i->created_at?->lokal()->format('d/m/Y') }}</div></td>
                                <td class="text-end">{{ $i->lines_count }}</td>
                                <td><span class="badge {{ $i->status->badge() }}">{{ $i->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="5">{{ __('Belum ada pemakaian material.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data->hasPages()) <div class="card-footer">{{ $data->links() }}</div> @endif
        </div>
    @elseif ($tab === 'konversi')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Konversi material') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Jenis') }}</th><th>{{ __('Gudang') }}</th><th>{{ __('Input → hasil') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data['cnv'] as $c)
                            <tr wire:key="cnv-{{ $c->id }}">
                                <td><a href="{{ route('conversions.show', $c) }}">{{ $c->number }}</a><div class="small text-muted">{{ $c->created_at?->lokal()->format('d/m/Y') }}</div></td>
                                <td>{{ $c->conversion_type->label() }}</td>
                                <td>{{ $c->warehouse?->code }}</td>
                                <td class="small">
                                    {{ $c->inputs->map(fn ($i) => $i->item?->code.' '.$angka($i->qty_base))->implode(', ') }}
                                    &rarr;
                                    {{ $c->outputs->groupBy(fn ($o) => $o->output_kind->value.'|'.$o->item_id)->map(fn ($g) => ($g->count() > 1 ? $g->count().' × ' : '').$angka($g->first()->qty_base).' '.$g->first()->item?->code.' ('.$g->first()->output_kind->label().')')->implode(', ') }}
                                </td>
                                <td><span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="5">{{ __('Belum ada konversi di proyek ini.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data['cnv']->hasPages()) <div class="card-footer">{{ $data['cnv']->links() }}</div> @endif
        </div>
        <div class="card">
            <div class="card-header"><strong>{{ __('Berita acara waste') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Gudang') }}</th><th>{{ __('Disposisi') }}</th><th class="text-end">{{ __('Baris') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data['wst'] as $w)
                            <tr wire:key="wst-{{ $w->id }}">
                                <td><a href="{{ route('waste-disposals.show', $w) }}">{{ $w->number }}</a></td>
                                <td>{{ $w->warehouse?->code }}</td>
                                <td>{{ $w->disposition?->label() }}</td>
                                <td class="text-end">{{ $w->lines_count }}</td>
                                <td><span class="badge {{ $w->status->badge() }}">{{ $w->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3" colspan="5">{{ __('Belum ada BA waste.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data['wst']->hasPages()) <div class="card-footer">{{ $data['wst']->links() }}</div> @endif
        </div>
    @elseif ($tab === 'retur')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ __('Retur dari proyek') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Pengaju') }}</th><th>{{ __('Ke gudang') }}</th><th class="text-end">{{ __('Baris') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data['ret'] as $r)
                            <tr wire:key="ret-{{ $r->id }}">
                                <td><a href="{{ route('returns.show', $r) }}">{{ $r->number }}</a><div class="small text-muted">{{ $r->created_at?->lokal()->format('d/m/Y') }}</div></td>
                                <td>{{ $r->requester?->name }}</td>
                                <td>{{ $r->toWarehouse?->code }}</td>
                                <td class="text-end">{{ $r->lines_count }}</td>
                                <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3" colspan="5">{{ __('Belum ada retur.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data['ret']->hasPages()) <div class="card-footer">{{ $data['ret']->links() }}</div> @endif
        </div>
        <div class="card">
            <div class="card-header"><strong>{{ __('Transfer') }}</strong> <span class="small text-muted">{{ __('masuk ke dan keluar dari proyek ini') }}</span></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Arah') }}</th><th>{{ __('Dari') }}</th><th>{{ __('Ke') }}</th><th class="text-end">{{ __('Baris') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data['trf'] as $t)
                            <tr wire:key="trf-{{ $t->id }}">
                                <td><a href="{{ route('transfers.show', $t) }}">{{ $t->number }}</a></td>
                                <td>
                                    @if ((int) $t->to_project_id === $project->id && (int) $t->from_project_id === $project->id)
                                        <span class="badge text-bg-light border">{{ __('Dalam proyek') }}</span>
                                    @elseif ((int) $t->to_project_id === $project->id)
                                        <span class="badge text-bg-success">{{ __('Masuk') }}</span>
                                    @else
                                        <span class="badge text-bg-warning">{{ __('Keluar') }}</span>
                                    @endif
                                </td>
                                <td>{{ $t->fromWarehouse?->code }} @if ($t->fromProject) <span class="small text-muted">({{ $t->fromProject->code }})</span> @endif</td>
                                <td>{{ $t->toWarehouse?->code }} @if ($t->toProject) <span class="small text-muted">({{ $t->toProject->code }})</span> @endif</td>
                                <td class="text-end">{{ $t->lines_count }}</td>
                                <td><span class="badge {{ $t->status->badge() }}">{{ $t->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3" colspan="6">{{ __('Belum ada transfer yang menyentuh proyek ini.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data['trf']->hasPages()) <div class="card-footer">{{ $data['trf']->links() }}</div> @endif
        </div>
    @elseif ($tab === 'aset')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Serah terima') }}</th><th>{{ __('Aset') }}</th><th>{{ __('Keluar') }}</th><th>{{ __('Jatuh tempo') }}</th><th>{{ __('Kembali') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data as $a)
                            <tr wire:key="ast-{{ $a->id }}">
                                <td><a href="{{ route('asset-handovers.show', $a) }}">{{ $a->number }}</a></td>
                                <td>
                                    @if ($a->serial) <a href="{{ route('assets.show', $a->serial) }}">{{ $a->serial->serial_no }}</a> @endif
                                    <div class="small text-muted">{{ $a->item?->code }} — {{ $a->item?->name }}</div>
                                </td>
                                <td>{{ $a->checked_out_at?->lokal()->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $a->due_return_date?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $a->returned_at?->lokal()->format('d/m/Y') ?? '—' }}</td>
                                <td><span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="6">{{ __('Belum ada aset dipinjamkan ke proyek ini.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($data->hasPages()) <div class="card-footer">{{ $data->links() }}</div> @endif
        </div>
    @elseif ($tab === 'approval')
        <div class="card">
            <div class="card-header"><strong>{{ __('Dokumen proyek yang menunggu approval') }}</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Dokumen') }}</th><th>{{ __('Aturan') }}</th><th>{{ __('Lapis') }}</th><th>{{ __('Menunggu') }}</th><th>{{ __('Diajukan') }}</th></tr></thead>
                    <tbody>
                        @forelse ($data as $a)
                            <tr wire:key="apr-{{ $a['nomor'] }}">
                                <td>
                                    @if ($a['url']) <a href="{{ $a['url'] }}">{{ $a['nomor'] }}</a> @else {{ $a['nomor'] }} @endif
                                    <div class="small text-muted">{{ $a['jenis'] }}</div>
                                </td>
                                <td class="small">{{ $a['aturan'] ?? '—' }}</td>
                                <td>{{ $a['lapis'] ?? '—' }}</td>
                                <td>{{ $a['approver'] ?: '—' }}</td>
                                <td class="small">{{ $a['diajukan'] ?? '—' }} · {{ $a['sejak']?->lokal()->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4" colspan="5">{{ __('Tidak ada dokumen proyek ini yang menunggu approval.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($tab === 'riwayat')
        <div class="card">
            <ul class="list-group list-group-flush small">
                @forelse ($data as $r)
                    <li class="list-group-item">{{ $r->created_at?->lokal()->format('d/m/Y H:i') }} · {{ $r->causer?->name ?? __('Sistem') }} · {{ $r->description }}</li>
                @empty
                    <li class="list-group-item text-muted">{{ __('Belum ada riwayat.') }}</li>
                @endforelse
            </ul>
            @if ($data->hasPages()) <div class="card-footer">{{ $data->links() }}</div> @endif
        </div>
    @endif
</div>
