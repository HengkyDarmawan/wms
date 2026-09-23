{{-- Sidebar NexaDash. Menu bertambah seiring modul (urutan Arsitektur §12). --}}
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

    <nav class="nx-sidebar-nav" id="nxSidebarNav">
        <div class="nx-menu-section">
            <div class="nx-menu-item">
                <a class="nx-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   href="{{ route('dashboard') }}" data-title="{{ __('Beranda') }}">
                    <i class="bi bi-grid-1x2"></i><span class="nx-menu-label">{{ __('Beranda') }}</span>
                </a>
            </div>
        </div>

        @canany(['user.view', 'role.view', 'org.view', 'support_access.grant'])
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Administrasi') }}
                </div>

                @can('user.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                           href="{{ route('users.index') }}" data-title="{{ __('Pengguna') }}">
                            <i class="bi bi-people"></i><span class="nx-menu-label">{{ __('Pengguna') }}</span>
                        </a>
                    </div>
                @endcan

                @can('role.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"
                           href="{{ route('roles.index') }}" data-title="{{ __('Role') }}">
                            <i class="bi bi-shield-lock"></i><span class="nx-menu-label">{{ __('Role') }}</span>
                        </a>
                    </div>
                @endcan

                @can('org.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('org.*') ? 'active' : '' }}"
                           href="{{ route('org.index') }}" data-title="{{ __('Struktur organisasi') }}">
                            <i class="bi bi-diagram-3"></i><span class="nx-menu-label">{{ __('Struktur organisasi') }}</span>
                        </a>
                    </div>
                @endcan

                @can('support_access.grant')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('support-access.*') ? 'active' : '' }}"
                           href="{{ route('support-access.index') }}" data-title="{{ __('Akses dukungan') }}">
                            <i class="bi bi-life-preserver"></i><span class="nx-menu-label">{{ __('Akses dukungan') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcanany

        @canany(['warehouse.view', 'bin.view', 'warehouse_type.view'])
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Gudang') }}
                </div>

                @can('warehouse.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}"
                           href="{{ route('warehouses.index') }}" data-title="{{ __('Daftar gudang') }}">
                            <i class="bi bi-buildings"></i><span class="nx-menu-label">{{ __('Daftar gudang') }}</span>
                        </a>
                    </div>
                @endcan

                @can('bin.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('bins.*') ? 'active' : '' }}"
                           href="{{ route('bins.index') }}" data-title="{{ __('Bin') }}">
                            <i class="bi bi-grid-3x3-gap"></i><span class="nx-menu-label">{{ __('Bin') }}</span>
                        </a>
                    </div>
                @endcan

                @can('warehouse_type.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('warehouse-types.*') ? 'active' : '' }}"
                           href="{{ route('warehouse-types.index') }}" data-title="{{ __('Tipe gudang') }}">
                            <i class="bi bi-diagram-2"></i><span class="nx-menu-label">{{ __('Tipe gudang') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcanany

        @can('request.view')
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Permintaan') }}
                </div>

                <div class="nx-menu-item">
                    <a class="nx-menu-link {{ request()->routeIs('requests.*') ? 'active' : '' }}"
                       href="{{ route('requests.index') }}" data-title="{{ __('Permintaan material') }}">
                        <i class="bi bi-clipboard-check"></i><span class="nx-menu-label">{{ __('Permintaan material') }}</span>
                    </a>
                </div>

                @can('request.create')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('requests.create') ? 'active' : '' }}"
                           href="{{ route('requests.create') }}" data-title="{{ __('Permintaan baru') }}">
                            <i class="bi bi-plus-square"></i><span class="nx-menu-label">{{ __('Permintaan baru') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcan

        @canany(['pick.view', 'shipment.view', 'discrepancy.view'])
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Pengiriman') }}
                </div>

                @can('pick.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('picks.*') ? 'active' : '' }}"
                           href="{{ route('picks.index') }}" data-title="{{ __('Tugas picking') }}">
                            <i class="bi bi-card-checklist"></i><span class="nx-menu-label">{{ __('Tugas picking') }}</span>
                        </a>
                    </div>
                @endcan

                @can('shipment.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('shipments.*') ? 'active' : '' }}"
                           href="{{ route('shipments.index') }}" data-title="{{ __('Surat jalan') }}">
                            <i class="bi bi-truck"></i><span class="nx-menu-label">{{ __('Surat jalan') }}</span>
                        </a>
                    </div>
                @endcan

                @can('discrepancy.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('discrepancies.*') ? 'active' : '' }}"
                           href="{{ route('discrepancies.index') }}" data-title="{{ __('Selisih pengiriman') }}">
                            <i class="bi bi-exclamation-diamond"></i><span class="nx-menu-label">{{ __('Selisih pengiriman') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcanany

        @canany(['stock.view', 'reservation.view', 'stock_event.view'])
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Stok') }}
                </div>

                @can('stock.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('stock.index') || request()->routeIs('stock.card') ? 'active' : '' }}"
                           href="{{ route('stock.index') }}" data-title="{{ __('Saldo stok') }}">
                            <i class="bi bi-boxes"></i><span class="nx-menu-label">{{ __('Saldo stok') }}</span>
                        </a>
                    </div>
                @endcan

                @can('reservation.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('stock.reservations') ? 'active' : '' }}"
                           href="{{ route('stock.reservations') }}" data-title="{{ __('Reservasi') }}">
                            <i class="bi bi-bookmark-check"></i><span class="nx-menu-label">{{ __('Reservasi') }}</span>
                        </a>
                    </div>
                @endcan

                @can('stock_event.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('stock.events') ? 'active' : '' }}"
                           href="{{ route('stock.events') }}" data-title="{{ __('Kejadian stok') }}">
                            <i class="bi bi-broadcast"></i><span class="nx-menu-label">{{ __('Kejadian stok') }}</span>
                        </a>
                    </div>
                @endcan

                @can('stock.lock_period')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('stock.period') ? 'active' : '' }}"
                           href="{{ route('stock.period') }}" data-title="{{ __('Kunci periode stok') }}">
                            <i class="bi bi-calendar-check"></i><span class="nx-menu-label">{{ __('Kunci periode') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcanany

        @canany(['item.view', 'item_category.view', 'project.view', 'client.view', 'vendor.view', 'uom.view', 'reference.view'])
            <div class="nx-menu-section">
                <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">
                    {{ __('Master data') }}
                </div>

                @can('item.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('items.*') ? 'active' : '' }}"
                           href="{{ route('items.index') }}" data-title="{{ __('Item') }}">
                            <i class="bi bi-box-seam"></i><span class="nx-menu-label">{{ __('Item') }}</span>
                        </a>
                    </div>
                @endcan

                @can('item_category.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('item-categories.*') ? 'active' : '' }}"
                           href="{{ route('item-categories.index') }}" data-title="{{ __('Kategori item') }}">
                            <i class="bi bi-tags"></i><span class="nx-menu-label">{{ __('Kategori item') }}</span>
                        </a>
                    </div>
                @endcan

                @can('uom.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('uoms.*') ? 'active' : '' }}"
                           href="{{ route('uoms.index') }}" data-title="{{ __('Satuan') }}">
                            <i class="bi bi-rulers"></i><span class="nx-menu-label">{{ __('Satuan') }}</span>
                        </a>
                    </div>
                @endcan

                @can('project.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                           href="{{ route('projects.index') }}" data-title="{{ __('Proyek') }}">
                            <i class="bi bi-signpost-split"></i><span class="nx-menu-label">{{ __('Proyek') }}</span>
                        </a>
                    </div>
                @endcan

                @can('client.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('clients.*') ? 'active' : '' }}"
                           href="{{ route('clients.index') }}" data-title="{{ __('Klien') }}">
                            <i class="bi bi-building"></i><span class="nx-menu-label">{{ __('Klien') }}</span>
                        </a>
                    </div>
                @endcan

                @can('vendor.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('vendors.*') ? 'active' : '' }}"
                           href="{{ route('vendors.index') }}" data-title="{{ __('Vendor') }}">
                            <i class="bi bi-truck"></i><span class="nx-menu-label">{{ __('Vendor') }}</span>
                        </a>
                    </div>
                @endcan

                @can('reference.view')
                    <div class="nx-menu-item">
                        <a class="nx-menu-link {{ request()->routeIs('references.*') ? 'active' : '' }}"
                           href="{{ route('references.index') }}" data-title="{{ __('Data referensi') }}">
                            <i class="bi bi-list-check"></i><span class="nx-menu-label">{{ __('Data referensi') }}</span>
                        </a>
                    </div>
                @endcan
            </div>
        @endcanany
        @canany(['user.view', 'item.view', 'project.view', 'warehouse.view', 'bin.view'])
            <div class="nx-menu-section">
                <div class="nx-menu-item">
                    <a class="nx-menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                       href="{{ route('reports.index') }}" data-title="{{ __('Laporan') }}">
                        <i class="bi bi-file-earmark-bar-graph"></i><span class="nx-menu-label">{{ __('Laporan') }}</span>
                    </a>
                </div>
            </div>
        @endcanany

        <div class="nx-menu-section">
            <div class="nx-menu-group-text px-3 pt-3 pb-1 small text-uppercase text-muted">{{ __('Akun') }}</div>
            <div class="nx-menu-item">
                <a class="nx-menu-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                   href="{{ route('profile.edit') }}" data-title="{{ __('Profil') }}">
                    <i class="bi bi-person"></i><span class="nx-menu-label">{{ __('Profil') }}</span>
                </a>
            </div>

            @canany(['device.view', 'device.manage'])
                <div class="nx-menu-item">
                    <a class="nx-menu-link {{ request()->routeIs('devices.*') ? 'active' : '' }}"
                       href="{{ route('devices.index') }}" data-title="{{ __('Perangkat') }}">
                        <i class="bi bi-phone"></i><span class="nx-menu-label">{{ __('Perangkat') }}</span>
                    </a>
                </div>
            @endcanany
        </div>
    </nav>
</aside>
