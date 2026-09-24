{{--
    Daftar halaman untuk pencarian cepat (Ctrl+K).

    Dikirim dari server supaya hanya menu yang benar-benar ada dan yang boleh
    dibuka user ini yang muncul. Template NexaDash aslinya membawa 72 tautan
    `.html` statis berbahasa Inggris; daftar itu sudah dibuang dari bundel JS.
--}}
@php
    $menu = collect([
        ['izin' => null, 'route' => 'dashboard', 'label' => __('Beranda'), 'ikon' => 'bi-grid-1x2'],
        ['izin' => 'warehouse.view', 'route' => 'warehouses.index', 'label' => __('Daftar gudang'), 'ikon' => 'bi-buildings'],
        ['izin' => 'bin.view', 'route' => 'bins.index', 'label' => __('Bin'), 'ikon' => 'bi-grid-3x3-gap'],
        ['izin' => 'warehouse_type.view', 'route' => 'warehouse-types.index', 'label' => __('Tipe gudang'), 'ikon' => 'bi-diagram-2'],
        ['izin' => 'request.view', 'route' => 'requests.index', 'label' => __('Permintaan material'), 'ikon' => 'bi-clipboard-check'],
        ['izin' => 'request.create', 'route' => 'requests.create', 'label' => __('Permintaan baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'pick.view', 'route' => 'picks.index', 'label' => __('Tugas picking'), 'ikon' => 'bi-card-checklist'],
        ['izin' => 'shipment.view', 'route' => 'shipments.index', 'label' => __('Surat jalan'), 'ikon' => 'bi-truck'],
        ['izin' => 'shipment.create', 'route' => 'shipments.create', 'label' => __('Surat jalan baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'discrepancy.view', 'route' => 'discrepancies.index', 'label' => __('Selisih pengiriman'), 'ikon' => 'bi-exclamation-diamond'],
        ['izin' => 'receipt.view', 'route' => 'receipts.index', 'label' => __('Penerimaan barang'), 'ikon' => 'bi-box-arrow-in-down'],
        ['izin' => 'receipt.create', 'route' => 'receipts.create', 'label' => __('Penerimaan baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'putaway.view', 'route' => 'putaways.index', 'label' => __('Tugas put-away'), 'ikon' => 'bi-inboxes'],
        ['izin' => 'vendor_return.view', 'route' => 'vendor-returns.index', 'label' => __('Retur ke vendor'), 'ikon' => 'bi-arrow-return-left'],
        ['izin' => 'vendor_return.create', 'route' => 'vendor-returns.create', 'label' => __('RTV baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'approval-inbox', 'route' => 'approval.inbox', 'label' => __('Tugas approval saya'), 'ikon' => 'bi-check2-square'],
        ['izin' => 'approval_rule.view', 'route' => 'approval.rules.index', 'label' => __('Aturan approval'), 'ikon' => 'bi-diagram-3-fill'],
        ['izin' => 'approval_rule.manage', 'route' => 'approval.rules.create', 'label' => __('Aturan approval baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'approval.delegate', 'route' => 'approval.delegations', 'label' => __('Delegasi approval'), 'ikon' => 'bi-person-check'],
        ['izin' => 'approval.simulate', 'route' => 'approval.simulation', 'label' => __('Simulasi approval'), 'ikon' => 'bi-signpost-2'],
        ['izin' => 'count.view', 'route' => 'counts.index', 'label' => __('Stock opname'), 'ikon' => 'bi-clipboard-data'],
        ['izin' => 'count.create', 'route' => 'counts.create', 'label' => __('Sesi opname baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'count.record', 'route' => 'count-tasks.index', 'label' => __('Hitungan saya'), 'ikon' => 'bi-123'],
        ['izin' => 'adjustment.view', 'route' => 'adjustments.index', 'label' => __('Penyesuaian stok'), 'ikon' => 'bi-plus-slash-minus'],
        ['izin' => 'adjustment.create', 'route' => 'adjustments.create', 'label' => __('Penyesuaian baru'), 'ikon' => 'bi-plus-square'],
        ['izin' => 'stock.view', 'route' => 'stock.index', 'label' => __('Saldo stok'), 'ikon' => 'bi-boxes'],
        ['izin' => 'reservation.view', 'route' => 'stock.reservations', 'label' => __('Reservasi'), 'ikon' => 'bi-bookmark-check'],
        ['izin' => 'stock_event.view', 'route' => 'stock.events', 'label' => __('Kejadian stok'), 'ikon' => 'bi-broadcast'],
        ['izin' => 'stock.lock_period', 'route' => 'stock.period', 'label' => __('Kunci periode stok'), 'ikon' => 'bi-calendar-check'],
        ['izin' => 'item.view', 'route' => 'items.index', 'label' => __('Item'), 'ikon' => 'bi-box-seam'],
        ['izin' => 'item_category.view', 'route' => 'item-categories.index', 'label' => __('Kategori item'), 'ikon' => 'bi-tags'],
        ['izin' => 'uom.view', 'route' => 'uoms.index', 'label' => __('Satuan'), 'ikon' => 'bi-rulers'],
        ['izin' => 'project.view', 'route' => 'projects.index', 'label' => __('Proyek'), 'ikon' => 'bi-signpost-split'],
        ['izin' => 'client.view', 'route' => 'clients.index', 'label' => __('Klien'), 'ikon' => 'bi-building'],
        ['izin' => 'vendor.view', 'route' => 'vendors.index', 'label' => __('Vendor'), 'ikon' => 'bi-truck'],
        ['izin' => 'reference.view', 'route' => 'references.index', 'label' => __('Data referensi'), 'ikon' => 'bi-list-check'],
        ['izin' => 'user.view', 'route' => 'users.index', 'label' => __('Pengguna'), 'ikon' => 'bi-people'],
        ['izin' => 'role.view', 'route' => 'roles.index', 'label' => __('Role'), 'ikon' => 'bi-shield-lock'],
        ['izin' => 'org.view', 'route' => 'org.index', 'label' => __('Struktur organisasi'), 'ikon' => 'bi-diagram-3'],
        ['izin' => 'support_access.grant', 'route' => 'support-access.index', 'label' => __('Akses dukungan'), 'ikon' => 'bi-life-preserver'],
        ['izin' => 'document_layout.manage', 'route' => 'document-layout.edit', 'label' => __('Layout dokumen'), 'ikon' => 'bi-file-earmark-richtext'],
        ['izin' => 'label.print', 'route' => 'labels.index', 'label' => __('Cetak label'), 'ikon' => 'bi-upc-scan'],
        ['izin' => null, 'route' => 'reports.index', 'label' => __('Laporan'), 'ikon' => 'bi-file-earmark-bar-graph'],
        ['izin' => null, 'route' => 'profile.edit', 'label' => __('Profil'), 'ikon' => 'bi-person'],
        ['izin' => 'device.view', 'route' => 'devices.index', 'label' => __('Perangkat'), 'ikon' => 'bi-phone'],
    ])
        ->filter(fn (array $item) => $item['izin'] === null || auth()->user()?->can($item['izin']))
        ->filter(fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route']))
        ->map(fn (array $item) => [
            't' => $item['label'],
            'u' => route($item['route']),
            'i' => $item['ikon'],
            'g' => 'Pages',
        ])
        ->values();
@endphp

<script type="application/json" id="nxPaletteData">@json($menu)</script>
<script>
    (function () {
        var el = document.getElementById('nxPaletteData');

        try {
            window.nxPalette = el ? JSON.parse(el.textContent) : [];
        } catch (e) {
            window.nxPalette = [];
        }
    })();
</script>
