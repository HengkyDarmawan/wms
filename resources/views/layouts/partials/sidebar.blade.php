{{--
    Sidebar NexaDash (10-access §6, A-227): dua butir atas, lalu grup lipat —
    hanya grup dengan halaman aktif yang terbuka (JS `initSidebarSections`
    mengingat pilihan pengguna). Kotak filter menyaring butir saat diketik.
    Satu grup = [judul, kunci, daftar butir]; butir = [izin (null = semua),
    nama rute, label, ikon, pola rute aktif]. Grup tampil bila ada butir
    yang boleh dilihat dan rutenya ada.
--}}
@php
    $user = auth()->user();
    $boleh = fn ($izin) => $izin === null || collect((array) $izin)->contains(fn ($i) => $user?->can($i));
    $klien = $user?->isClient() ?? false;

    $grup = [
        ['Proyek', 'proyek', [
            ['project.view', 'projects.index', __('Proyek'), 'bi-signpost-split', 'projects.*'],
        ]],
        ['Gudang & stok', 'stok', [
            ['stock.view', 'stock.index', __('Saldo stok'), 'bi-boxes', 'stock.index'],
            ['reservation.view', 'stock.reservations', __('Reservasi'), 'bi-bookmark-check', 'stock.reservations'],
            ['stock_event.view', 'stock.events', __('Kejadian stok'), 'bi-broadcast', 'stock.events'],
            ['stock.lock_period', 'stock.period', __('Kunci periode'), 'bi-calendar-check', 'stock.period'],
            ['warehouse.view', 'warehouses.index', __('Daftar gudang'), 'bi-buildings', 'warehouses.*'],
            ['bin.view', 'bins.index', __('Bin'), 'bi-grid-3x3-gap', 'bins.*'],
            ['warehouse_type.view', 'warehouse-types.index', __('Tipe gudang'), 'bi-diagram-2', 'warehouse-types.*'],
        ]],
        ['Barang keluar', 'keluar', [
            ['request.view', $klien ? 'portal.requests.index' : 'requests.index', __('Permintaan material'), 'bi-clipboard-check', 'requests.*'],
            ['pick.view', 'picks.index', __('Tugas picking'), 'bi-card-checklist', 'picks.*'],
            ['shipment.view', 'shipments.index', __('Surat jalan'), 'bi-truck', 'shipments.*'],
            ['discrepancy.view', 'discrepancies.index', __('Selisih pengiriman'), 'bi-exclamation-diamond', 'discrepancies.*'],
        ]],
        ['Barang masuk', 'masuk', [
            ['receipt.view', 'receipts.index', __('Penerimaan barang'), 'bi-box-arrow-in-down', 'receipts.*'],
            ['putaway.view', 'putaways.index', __('Tugas put-away'), 'bi-inboxes', 'putaways.*'],
            ['vendor_return.view', 'vendor-returns.index', __('Retur ke vendor'), 'bi-arrow-return-left', 'vendor-returns.*'],
            ['transfer.view', 'transfers.index', __('Transfer'), 'bi-arrow-left-right', 'transfers.*'],
            ['return.view', $klien ? 'portal.returns.index' : 'returns.index', __('Retur dari proyek'), 'bi-arrow-counterclockwise', 'returns.*'],
        ]],
        ['Di proyek', 'proyek-site', [
            ['issue.view', 'issues.index', __('Pemakaian material'), 'bi-hammer', 'issues.*'],
            ['conversion.view', 'conversions.index', __('Konversi material'), 'bi-scissors', 'conversions.*'],
            ['waste.view', 'waste-disposals.index', __('Berita acara waste'), 'bi-trash3', 'waste-disposals.*'],
            ['asset.view', 'assets.index', __('Aset'), 'bi-truck-front', 'assets.*'],
            ['asset.view', 'asset-handovers.index', __('Serah terima aset'), 'bi-box-arrow-right', 'asset-handovers.*'],
        ]],
        ['Pembelian', 'beli', [
            ['pr.view', 'purchase-requests.index', __('Purchase Request'), 'bi-cart3', 'purchase-requests.*'],
            ['po.view', 'purchase-orders.index', __('Purchase Order'), 'bi-receipt-cutoff', 'purchase-orders.*'],
            ['vendor_price.view', 'vendor-prices.index', __('Harga beli vendor'), 'bi-cash-coin', 'vendor-prices.*'],
        ]],
        ['Opname & penyesuaian', 'opname', [
            ['count.view', 'counts.index', __('Stock opname'), 'bi-clipboard-data', 'counts.*'],
            ['count.record', 'count-tasks.index', __('Hitungan saya'), 'bi-123', 'count-tasks.*'],
            ['adjustment.view', 'adjustments.index', __('Penyesuaian stok'), 'bi-plus-slash-minus', 'adjustments.*'],
        ]],
        ['Master data', 'master', [
            ['item.view', 'items.index', __('Item'), 'bi-box-seam', 'items.*'],
            ['item_category.view', 'item-categories.index', __('Kategori item'), 'bi-tags', 'item-categories.*'],
            ['uom.view', 'uoms.index', __('Satuan'), 'bi-rulers', 'uoms.*'],
            ['client.view', 'clients.index', __('Klien'), 'bi-building', 'clients.*'],
            ['vendor.view', 'vendors.index', __('Vendor'), 'bi-shop', 'vendors.*'],
            ['reference.view', 'references.index', __('Data referensi'), 'bi-list-check', 'references.*'],
        ]],
        ['Laporan & cetak', 'laporan', [
            [null, 'reports.index', __('Laporan'), 'bi-file-earmark-bar-graph', 'reports.*'],
            ['label.print', 'labels.index', __('Cetak label'), 'bi-upc-scan', 'labels.*'],
            [['item.create', 'project.create', 'vendor.create', 'adjustment.create'], 'imports.index', __('Impor Excel'), 'bi-file-earmark-spreadsheet', 'imports.*'],
        ]],
        ['Pengaturan', 'pengaturan', [
            ['user.view', 'users.index', __('Pengguna'), 'bi-people', 'users.*'],
            ['role.view', 'roles.index', __('Role'), 'bi-shield-lock', 'roles.*'],
            ['org.view', 'org.index', __('Struktur organisasi'), 'bi-diagram-3', 'org.*'],
            [['approval_rule.view', 'approval_rule.manage'], 'approval.rules.index', __('Aturan approval'), 'bi-diagram-3-fill', 'approval.rules.*'],
            [['approval.delegate', 'approval_rule.manage'], 'approval.delegations', __('Delegasi approval'), 'bi-person-check', 'approval.delegations'],
            ['approval.simulate', 'approval.simulation', __('Simulasi approval'), 'bi-signpost-2', 'approval.simulation'],
            ['company_setting.manage', 'settings.company', __('Pengaturan company'), 'bi-sliders', 'settings.company'],
            ['document_layout.manage', 'document-layout.edit', __('Layout dokumen'), 'bi-file-earmark-richtext', 'document-layout.*'],
            ['support_access.grant', 'support-access.index', __('Akses dukungan'), 'bi-life-preserver', 'support-access.*'],
            ['company_setting.manage', 'setup.index', __('Setup awal'), 'bi-rocket-takeoff', 'setup.*'],
            ['billing.view', 'billing.index', __('Tagihan langganan'), 'bi-receipt', 'billing.*'],
        ]],
        ['Akun', 'akun', [
            [null, 'profile.edit', __('Profil'), 'bi-person', 'profile.*'],
            [['device.view', 'device.manage'], 'devices.index', __('Perangkat'), 'bi-phone', 'devices.*'],
        ]],
    ];

    $tugasApproval = $user?->can('approval-inbox')
        ? \App\Domain\Approval\Models\ApprovalTask::query()->open()
            ->where('approver_user_id', $user->id)
            ->whereHas('snapshot', fn ($q) => $q->where('status', 'pending'))
            ->count()
        : 0;
@endphp
<aside class="nx-sidebar" id="nxSidebar" aria-label="{{ __('Navigasi utama') }}">
    <div class="nx-sidebar-head">
        <a class="nx-sidebar-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('img/logo.svg') }}" alt="{{ config('app.name') }}">
            <span class="nx-brand-text">{{ config('app.name') }}</span>
        </a>
        <button class="nx-icon-btn nx-sidebar-close" id="nxSidebarClose" type="button"
                aria-label="{{ __('Tutup navigasi') }}">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="nx-sidebar-search">
        <i class="bi bi-search"></i>
        <input type="search" id="nxMenuFilter" class="nx-sidebar-search-input"
               placeholder="{{ __('Cari menu…') }}" autocomplete="off" aria-label="{{ __('Cari menu') }}"
               aria-controls="nxSidebarNav">
        <button type="button" class="nx-sidebar-search-clear" id="nxMenuFilterClear" aria-label="{{ __('Hapus filter') }}" hidden>
            <i class="bi bi-x"></i>
        </button>
    </div>

    <nav class="nx-sidebar-nav" id="nxSidebarNav">
        <div class="nx-menu-section" data-nx-section="atas">
            <div class="nx-section-body show" id="nxSecAtas">
                <div class="nx-menu-item">
                    <a class="nx-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       href="{{ route('dashboard') }}" data-title="{{ __('Beranda') }}">
                        <i class="bi bi-grid-1x2"></i><span class="nx-menu-label">{{ __('Beranda') }}</span>
                    </a>
                </div>
                @can('approval-inbox')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('approval.inbox') ? 'active' : '' }}"
                           href="{{ route('approval.inbox') }}" data-title="{{ __('Tugas approval saya') }}">
                            <i class="bi bi-check2-square"></i><span class="nx-menu-label">{{ __('Tugas approval saya') }}</span>
                            @if ($tugasApproval > 0)
                                <span class="badge text-bg-warning ms-auto">{{ $tugasApproval }}</span>
                            @endif
                        </a>
                    </div>
                @endcan
            </div>
        </div>

        @foreach ($grup as [$judul, $kunci, $butir])
            @php($tampil = collect($butir)->filter(fn ($b) => $boleh($b[0]) && Route::has($b[1])))
            @if ($tampil->isNotEmpty())
                @php($idSeksi = 'nxSec'.\Illuminate\Support\Str::studly($kunci))
                <div class="nx-menu-section" data-nx-section="{{ $kunci }}">
                    <button class="nx-menu-group" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $idSeksi }}"
                            aria-expanded="false" aria-controls="{{ $idSeksi }}">
                        <span class="nx-menu-group-text">{{ __($judul) }}</span><i class="bi bi-chevron-down nx-group-chevron"></i>
                    </button>
                    <div class="collapse nx-section-body" id="{{ $idSeksi }}">
                        @foreach ($tampil as [$izin, $rute, $label, $ikon, $pola])
                            <div class="nx-menu-item">
                                <a class="nx-menu-link {{ request()->routeIs($pola) ? 'active' : '' }}"
                                   href="{{ route($rute) }}" data-title="{{ $label }}">
                                    <i class="bi {{ $ikon }}"></i><span class="nx-menu-label">{{ $label }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
