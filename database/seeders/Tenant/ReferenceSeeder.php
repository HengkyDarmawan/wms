<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data referensi setiap company baru: permission modul dan role bawaan
 * (Blueprint §4.2, 10-access §2). Aman dijalankan berulang.
 */
class ReferenceSeeder extends Seeder
{
    /** @var array<string, array<string, string>> modul => [kunci => label] */
    private const PERMISSIONS = [
        'auth' => [
            'auth.login' => 'Masuk',
            'auth.logout' => 'Keluar',
            'auth.two_factor' => 'Verifikasi dua langkah',
        ],
        'profile' => [
            'profile.update' => 'Ubah profil sendiri',
        ],
        'user' => [
            'user.view' => 'Lihat pengguna',
            'user.create' => 'Tambah pengguna',
            'user.update' => 'Ubah pengguna',
            'user.deactivate' => 'Nonaktifkan pengguna',
            'user.invite' => 'Kirim undangan',
            'user.reset_password' => 'Atur ulang password pengguna',
            'user.impersonate' => 'Masuk sebagai pengguna lain',
        ],
        'role' => [
            'role.view' => 'Lihat role',
            'role.create' => 'Tambah role',
            'role.update' => 'Ubah role',
            'role.deactivate' => 'Nonaktifkan role',
            'role.assign' => 'Menugaskan role & cakupan',
        ],
        'org' => [
            'org.view' => 'Lihat struktur organisasi',
            'org.manage' => 'Kelola struktur organisasi',
        ],
        'device' => [
            'device.view' => 'Lihat perangkat',
            'device.manage' => 'Kelola perangkat',
        ],
        'support_access' => [
            'support_access.grant' => 'Beri akses dukungan',
            'support_access.revoke' => 'Cabut akses dukungan',
        ],
        // Modul Master (11-master §2).
        'master' => [
            'client.view' => 'Lihat klien',
            'client.create' => 'Tambah klien',
            'client.update' => 'Ubah klien',
            'client.deactivate' => 'Nonaktifkan klien',
            'project.view' => 'Lihat proyek',
            'project.create' => 'Tambah proyek',
            'project.update' => 'Ubah proyek',
            'project.close' => 'Tutup, batalkan, atau arsipkan proyek',
            'vendor.view' => 'Lihat vendor',
            'vendor.create' => 'Tambah vendor',
            'vendor.update' => 'Ubah vendor',
            'vendor.deactivate' => 'Nonaktifkan vendor',
            'item.view' => 'Lihat item',
            'item.create' => 'Tambah item',
            'item.update' => 'Ubah item',
            'item.deactivate' => 'Nonaktifkan item',
            'item_category.view' => 'Lihat kategori item',
            'item_category.manage' => 'Kelola kategori item',
            'uom.view' => 'Lihat satuan',
            'uom.manage' => 'Kelola satuan',
            'reference.view' => 'Lihat data referensi',
            'reference.manage' => 'Kelola data referensi',
            'company_setting.view' => 'Lihat pengaturan company',
            'company_setting.manage' => 'Ubah pengaturan company',
        ],
        // Modul Warehouse (12-warehouse §2).
        'warehouse' => [
            'warehouse.view' => 'Lihat gudang',
            'warehouse.create' => 'Tambah gudang',
            'warehouse.update' => 'Ubah gudang',
            'warehouse.deactivate' => 'Nonaktifkan gudang',
            'bin.view' => 'Lihat bin',
            'bin.manage' => 'Kelola zona, rak, dan bin',
            'warehouse_type.view' => 'Lihat tipe gudang',
            'warehouse_type.manage' => 'Kelola tipe gudang',
        ],
        // Modul Stock (13-stock §2). Tidak ada `stock.post`: memposting stok
        // adalah akibat dokumen, izinnya melekat pada aksi dokumen.
        'stock' => [
            'stock.view' => 'Lihat saldo dan kartu stok',
            'stock.lock_period' => 'Kunci periode stok',
            'reservation.view' => 'Lihat reservasi',
            'reservation.release' => 'Lepas reservasi',
            'stock_event.view' => 'Lihat outbox kejadian stok',
        ],
        // Modul Request (14-request §2). `request.approve` dipakai modul
        // approval; siapa yang berhak ditentukan aturan approval, bukan role.
        'request' => [
            'request.view' => 'Lihat permintaan material',
            'request.create' => 'Buat permintaan material',
            'request.submit' => 'Ajukan permintaan material',
            'request.review' => 'Tinjau permintaan material',
            'request.approve' => 'Setujui permintaan material',
            'request.split_line' => 'Pecah baris ke beberapa gudang',
            'request.add_lines' => 'Tambah baris permintaan',
            'request.respond_substitution' => 'Tanggapi penggantian item',
            'request.request_cancel' => 'Minta pembatalan baris',
            'request.confirm_cancel' => 'Konfirmasi pembatalan baris',
            'request.close_short' => 'Tutup permintaan dengan sisa',
            'request.cancel' => 'Batalkan permintaan material',
            'request.confirm_receipt' => 'Konfirmasi terima barang',
            'request.dispute_receipt' => 'Ajukan keberatan penerimaan',
        ],
        // Modul Picking (15-picking-shipment §2).
        'picking' => [
            'pick.view' => 'Lihat tugas picking',
            'pick.create' => 'Buat tugas picking',
            'pick.start' => 'Mulai picking',
            'pick.complete' => 'Selesaikan picking',
            'pick.cancel' => 'Batalkan tugas picking',
        ],
        // Modul Shipment (15-picking-shipment §2).
        'shipment' => [
            'shipment.view' => 'Lihat surat jalan',
            'shipment.create' => 'Susun surat jalan',
            'shipment.ship' => 'Berangkatkan surat jalan',
            'shipment.confirm_delivery' => 'Isi bukti terima',
            'shipment.cancel' => 'Batalkan surat jalan',
            'discrepancy.view' => 'Lihat selisih pengiriman',
            'discrepancy.resolve' => 'Selesaikan selisih pengiriman',
        ],
        // Modul Receipt/Putaway (19-receipt-putaway §2).
        'receipt' => [
            'receipt.view' => 'Lihat penerimaan barang',
            'receipt.create' => 'Buat penerimaan barang',
            'receipt.receive' => 'Terima barang (posting stok)',
            'receipt.qc' => 'Catat hasil QC',
            'receipt.complete' => 'Selesaikan penerimaan barang',
            'receipt.cancel' => 'Batalkan penerimaan barang',
        ],
        'putaway' => [
            'putaway.view' => 'Lihat tugas put-away',
            'putaway.complete' => 'Selesaikan put-away',
            'putaway.cancel' => 'Batalkan tugas put-away',
        ],
        'vendor_return' => [
            'vendor_return.view' => 'Lihat retur ke vendor',
            'vendor_return.create' => 'Ajukan retur ke vendor',
            'vendor_return.approve' => 'Setujui retur ke vendor',
            'vendor_return.ship' => 'Kirim retur ke vendor',
            'vendor_return.complete' => 'Selesaikan retur ke vendor',
            'vendor_return.cancel' => 'Batalkan retur ke vendor',
        ],
        // Modul Count/Adjustment (21-opname-penyesuaian §2). Katalog §2.12–§2.13
        // menulis create/start/reconcile/approve/cancel dan create/approve/cancel;
        // view, assign, record ditambah (A-95).
        'count' => [
            'count.view' => 'Lihat sesi stock opname',
            'count.create' => 'Rencanakan sesi opname',
            'count.start' => 'Mulai sesi opname (bekukan bin)',
            'count.assign' => 'Tugaskan penghitung',
            'count.record' => 'Input hitungan (hitung buta)',
            'count.reconcile' => 'Rekonsiliasi & ajukan hasil opname',
            'count.approve' => 'Setujui hasil opname',
            'count.cancel' => 'Batalkan sesi opname',
        ],
        'adjustment' => [
            'adjustment.view' => 'Lihat penyesuaian stok',
            'adjustment.create' => 'Ajukan penyesuaian stok',
            'adjustment.approve' => 'Setujui penyesuaian stok',
            'adjustment.cancel' => 'Batalkan penyesuaian stok',
        ],
        // Modul Transfer & Retur (22-retur-transfer §2). Katalog §2.7–§2.8 menulis
        // transfer.create/approve/cancel dan return.create/approve/sort/cancel;
        // izin melihat transfer.view dan return.view ditambah (A-109).
        'transfer' => [
            'transfer.view' => 'Lihat transfer',
            'transfer.create' => 'Ajukan transfer',
            'transfer.approve' => 'Setujui transfer',
            'transfer.cancel' => 'Batalkan transfer',
        ],
        'return' => [
            'return.view' => 'Lihat retur dari proyek',
            'return.create' => 'Ajukan retur dari proyek',
            'return.approve' => 'Setujui retur dari proyek',
            'return.sort' => 'Pilah barang retur',
            'return.cancel' => 'Batalkan retur dari proyek',
        ],
        // Modul Issue — pemakaian material di site (23-pemakaian §2). Katalog §2.9
        // menulis issue.create/confirm/cancel; issue.view dan issue.approve
        // (keputusan ISU pembalik, BR-GEN-04) ditambah (A-119).
        'issue' => [
            'issue.view' => 'Lihat pemakaian material',
            'issue.create' => 'Catat pemakaian material',
            'issue.confirm' => 'Konfirmasi pemakaian material',
            'issue.cancel' => 'Batalkan pemakaian material',
            'issue.approve' => 'Setujui ISU pembalik',
        ],
        // Modul Konversi & Waste (24-konversi-waste §2). Katalog §2.10 dan §2.14
        // menulis conversion.create/submit/complete/approve/cancel dan
        // waste.create/approve/close/cancel; izin melihat ditambah (A-158).
        'conversion' => [
            'conversion.view' => 'Lihat konversi material',
            'conversion.create' => 'Buat konversi material',
            'conversion.submit' => 'Ajukan konversi ke approval',
            'conversion.complete' => 'Selesaikan konversi material',
            'conversion.approve' => 'Setujui konversi material',
            'conversion.cancel' => 'Batalkan konversi material',
        ],
        // Modul Aset dipinjamkan (25-aset §2). Katalog §2.11 menulis asset.inspect,
        // matriks §14 asset.mark_lost; asset.view dan asset.manage ditambah (A-168).
        'asset' => [
            'asset.view' => 'Lihat aset & serah terima',
            'asset.manage' => 'Kelola profil aset & serah terima',
            'asset.inspect' => 'Periksa aset kembali',
            'asset.mark_lost' => 'Tandai aset hilang',
        ],
        'waste' => [
            'waste.view' => 'Lihat berita acara waste',
            'waste.create' => 'Ajukan berita acara waste',
            'waste.approve' => 'Setujui berita acara waste',
            'waste.close' => 'Tutup berita acara waste',
            'waste.cancel' => 'Batalkan berita acara waste',
        ],
        // Tagihan langganan di sisi company (17-platform-login §2, A-178):
        // hanya Admin Company (lewat `*`).
        'billing' => [
            'billing.view' => 'Lihat tagihan langganan',
            'billing.pay' => 'Unggah bukti bayar langganan',
        ],
        // Modul Purchase Request (26-purchase-request §2, Katalog §2.15).
        'purchase_request' => [
            'pr.view' => 'Lihat Purchase Request',
            'pr.create' => 'Buat PRQ manual',
            'pr.submit' => 'Tinjau & ajukan draf PRQ titik pesan ulang',
            'pr.approve' => 'Setujui Purchase Request',
            'pr.order' => 'Catat pemesanan PRQ (teruskan ke Purchasing)',
            'pr.cancel' => 'Batalkan Purchase Request',
        ],
        // Purchasing inti Fase 1b (purchasing/02 §2, Katalog §2.17, A-216).
        // Satu-satunya permission yang membuka nilai uang (A-208).
        'purchase_order' => [
            'po.view' => 'Lihat Purchase Order & nilainya',
            'po.create' => 'Buat & ubah PO (termasuk ETA)',
            'po.submit' => 'Ajukan PO',
            'po.approve' => 'Setujui Purchase Order',
            'po.cancel' => 'Batalkan PO',
            'po.close' => 'Tutup sisa PO',
        ],
        'vendor_price' => [
            'vendor_price.view' => 'Lihat harga beli vendor',
            'vendor_price.manage' => 'Kelola harga beli vendor',
        ],
        // Modul Approval (20-approval §2). Keputusan memakai permission approve
        // Katalog per dokumen (`request.approve`, `vendor_return.approve`, A-86).
        'approval' => [
            'approval_rule.view' => 'Lihat aturan approval',
            'approval_rule.manage' => 'Kelola aturan approval',
            'approval.simulate' => 'Simulasi aturan approval',
            'approval.delegate' => 'Kelola delegasi approval',
            'approval.escalate' => 'Eskalasi tugas approval',
        ],
        // Modul Template dokumen & label (18-template-dokumen-label §2). Cetak
        // dokumen memakai izin lihat dokumennya, tanpa permission sendiri (A-124).
        'template' => [
            'document_layout.manage' => 'Kelola layout dokumen cetak',
            'label.print' => 'Cetak label bin & barang',
        ],
    ];

    /** @var array<string, array{name: string, client: bool, permissions: array<int, string>|string}> */
    private const ROLES = [
        'company_admin' => [
            'name' => 'Admin Company',
            'client' => false,
            'permissions' => '*',
        ],
        'management' => [
            'name' => 'Manajemen',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'user.view', 'role.view', 'org.view', 'client.view', 'project.view', 'vendor.view', 'item.view', 'item_category.view', 'uom.view', 'reference.view', 'company_setting.view', 'warehouse.view', 'bin.view', 'warehouse_type.view', 'stock.view', 'reservation.view', 'stock_event.view', 'request.view', 'pick.view', 'shipment.view', 'discrepancy.view', 'receipt.view', 'putaway.view', 'vendor_return.view', 'request.approve', 'vendor_return.approve', 'approval_rule.view', 'approval.simulate', 'approval.delegate', 'count.view', 'count.approve', 'adjustment.view', 'adjustment.approve', 'transfer.view', 'transfer.approve', 'return.view', 'return.approve', 'issue.view', 'issue.approve', 'conversion.view', 'conversion.approve', 'waste.view', 'waste.approve', 'asset.view', 'pr.view', 'pr.approve', 'po.view', 'po.approve', 'vendor_price.view'],
        ],
        'warehouse_head' => [
            'name' => 'Kepala Gudang',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'user.view', 'device.view', 'org.view', 'client.view', 'project.view', 'vendor.view', 'item.view', 'item.update', 'item_category.view', 'uom.view', 'reference.view', 'reference.manage', 'warehouse.view', 'warehouse.update', 'bin.view', 'bin.manage', 'warehouse_type.view', 'stock.view', 'reservation.view', 'reservation.release', 'request.view', 'request.review', 'request.split_line', 'request.close_short', 'request.cancel', 'request.confirm_cancel', 'pick.view', 'pick.create', 'pick.start', 'pick.complete', 'pick.cancel', 'shipment.view', 'shipment.create', 'shipment.ship', 'shipment.cancel', 'discrepancy.view', 'discrepancy.resolve', 'receipt.view', 'receipt.create', 'receipt.receive', 'receipt.qc', 'receipt.complete', 'receipt.cancel', 'putaway.view', 'putaway.complete', 'putaway.cancel', 'vendor_return.view', 'vendor_return.create', 'vendor_return.approve', 'vendor_return.ship', 'vendor_return.complete', 'vendor_return.cancel', 'request.approve', 'approval_rule.view', 'approval.delegate', 'count.view', 'count.create', 'count.start', 'count.assign', 'count.record', 'count.reconcile', 'count.approve', 'count.cancel', 'adjustment.view', 'adjustment.create', 'adjustment.approve', 'adjustment.cancel', 'transfer.view', 'transfer.create', 'transfer.approve', 'transfer.cancel', 'return.view', 'return.create', 'return.approve', 'return.sort', 'return.cancel', 'issue.view', 'issue.create', 'issue.confirm', 'issue.cancel', 'issue.approve', 'conversion.view', 'conversion.create', 'conversion.submit', 'conversion.complete', 'conversion.approve', 'conversion.cancel', 'waste.view', 'waste.create', 'waste.approve', 'waste.close', 'waste.cancel', 'asset.view', 'asset.manage', 'asset.inspect', 'asset.mark_lost', 'label.print', 'pr.view', 'pr.create', 'pr.submit', 'pr.approve', 'pr.cancel'],
        ],
        'warehouse_staff' => [
            'name' => 'Staf Gudang',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'device.manage', 'item.view', 'item_category.view', 'uom.view', 'project.view', 'reference.view', 'warehouse.view', 'bin.view', 'stock.view', 'reservation.view', 'request.view', 'request.review', 'request.split_line', 'request.confirm_cancel', 'pick.view', 'pick.start', 'pick.complete', 'shipment.view', 'shipment.create', 'shipment.ship', 'receipt.view', 'receipt.create', 'receipt.receive', 'receipt.qc', 'receipt.complete', 'putaway.view', 'putaway.complete', 'vendor_return.view', 'vendor_return.create', 'vendor_return.ship', 'vendor_return.cancel', 'count.view', 'count.record', 'adjustment.view', 'adjustment.create', 'adjustment.cancel', 'transfer.view', 'transfer.create', 'transfer.cancel', 'return.view', 'return.create', 'return.sort', 'return.cancel', 'issue.view', 'issue.create', 'issue.confirm', 'issue.cancel', 'conversion.view', 'conversion.create', 'conversion.submit', 'conversion.complete', 'conversion.cancel', 'waste.view', 'waste.create', 'waste.close', 'waste.cancel', 'asset.view', 'asset.inspect', 'label.print', 'pr.view', 'pr.create'],
        ],
        'driver' => [
            'name' => 'Driver',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'profile.update', 'device.manage', 'item.view', 'project.view', 'warehouse.view', 'stock.view', 'pick.view', 'shipment.view', 'shipment.ship', 'shipment.confirm_delivery'],
        ],
        'internal_requester' => [
            'name' => 'Pemohon Internal',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'device.manage', 'item.view', 'project.view', 'warehouse.view', 'stock.view', 'request.view', 'request.create', 'request.submit', 'request.cancel', 'request.close_short', 'request.confirm_receipt', 'request.dispute_receipt', 'return.view', 'return.create', 'return.cancel', 'issue.view', 'issue.create', 'issue.cancel'],
        ],
        'pr_follow_up' => [
            'name' => 'Penindak Lanjut PR',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'item.view', 'project.view', 'vendor.view', 'vendor.create', 'warehouse.view', 'stock.view', 'receipt.view', 'vendor_return.view', 'vendor_return.complete', 'pr.view', 'pr.order', 'pr.cancel', 'po.view', 'po.create', 'po.submit', 'po.cancel', 'po.close', 'vendor_price.view', 'vendor_price.manage'],
        ],
        'internal_auditor' => [
            'name' => 'Auditor Internal',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'auth.two_factor', 'profile.update', 'user.view', 'org.view', 'item.view', 'project.view', 'vendor.view', 'client.view', 'item_category.view', 'uom.view', 'reference.view', 'warehouse.view', 'bin.view', 'warehouse_type.view', 'stock.view', 'reservation.view', 'stock_event.view', 'request.view', 'pick.view', 'shipment.view', 'discrepancy.view', 'receipt.view', 'putaway.view', 'vendor_return.view', 'approval_rule.view', 'count.view', 'count.create', 'count.start', 'count.assign', 'count.record', 'count.reconcile', 'count.approve', 'count.cancel', 'adjustment.view', 'transfer.view', 'return.view', 'issue.view', 'conversion.view', 'waste.view', 'asset.view', 'pr.view', 'po.view', 'vendor_price.view'],
        ],
        'external_auditor' => [
            'name' => 'Auditor Eksternal',
            'client' => false,
            'permissions' => ['auth.login', 'auth.logout', 'profile.update', 'item.view', 'project.view', 'request.view', 'pick.view', 'shipment.view', 'discrepancy.view', 'receipt.view', 'putaway.view', 'vendor_return.view', 'count.view', 'count.record', 'adjustment.view', 'transfer.view', 'return.view', 'issue.view', 'conversion.view', 'waste.view', 'asset.view', 'pr.view'],
        ],
        'client_user' => [
            'name' => 'Klien',
            'client' => true,
            'permissions' => ['auth.login', 'auth.logout', 'profile.update', 'request.view', 'request.create', 'request.submit', 'request.cancel', 'request.add_lines', 'request.respond_substitution', 'request.request_cancel', 'request.confirm_receipt', 'request.dispute_receipt', 'shipment.view', 'return.view', 'return.create', 'return.cancel'],
        ],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $module => $items) {
            foreach ($items as $key => $label) {
                $permission = Permission::updateOrCreate(
                    ['name' => $key, 'guard_name' => 'web'],
                    ['module' => $module, 'label' => $label],
                );

                $permissionIds[$key] = $permission->id;
            }
        }

        foreach (self::ROLES as $code => $definition) {
            $role = Role::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'guard_name' => 'web',
                    'is_builtin' => true,
                    'is_client_role' => $definition['client'],
                    'is_active' => true,
                ],
            );

            $keys = $definition['permissions'] === '*'
                ? array_keys($permissionIds)
                : $definition['permissions'];

            $role->permissions()->sync(
                array_values(array_intersect_key($permissionIds, array_flip($keys)))
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Referensi tenant siap: '.count($permissionIds).' permission, '.count(self::ROLES).' role bawaan.');
    }
}
