<?php

declare(strict_types=1);

use App\Http\Controllers\Access\DeviceController;
use App\Http\Controllers\Access\InvitationController;
use App\Http\Controllers\Access\LoginController;
use App\Http\Controllers\Access\OrgController;
use App\Http\Controllers\Access\PasswordResetController;
use App\Http\Controllers\Access\ProfileController;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Access\SupportAccessController;
use App\Http\Controllers\Access\TwoFactorController;
use App\Http\Controllers\Access\TwoFactorSetupController;
use App\Http\Controllers\Access\UserController;
use App\Http\Controllers\Adjustment\AdjustmentController;
use App\Http\Controllers\Approval\ApprovalController;
use App\Http\Controllers\Asset\AssetController;
use App\Http\Controllers\Conversion\ConversionController;
use App\Http\Controllers\Count\CountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Issue\IssueController;
use App\Http\Controllers\Master\ClientController;
use App\Http\Controllers\Master\ImportController;
use App\Http\Controllers\Master\ItemCategoryController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\ItemPhotoController;
use App\Http\Controllers\Master\ProjectController;
use App\Http\Controllers\Master\ReferenceController;
use App\Http\Controllers\Master\CompanySettingsController;
use App\Http\Controllers\Master\SetupController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\VendorController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Platform\BillingController;
use App\Http\Controllers\Platform\SupportSessionController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\PurchaseRequest\PurchaseRequestController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Receipt\ReceiptController;
use App\Http\Controllers\Request\DeliveryReceiptController;
use App\Http\Controllers\Request\PortalRequestController;
use App\Http\Controllers\Request\RequestController;
use App\Http\Controllers\Return\ReturnController;
use App\Http\Controllers\Shared\FileController;
use App\Http\Controllers\Shared\ReportController;
use App\Http\Controllers\Shipment\DeliveryTokenController;
use App\Http\Controllers\Shipment\ShipmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Template\DocumentLayoutController;
use App\Http\Controllers\Template\PrintController;
use App\Http\Controllers\Transfer\TransferController;
use App\Http\Controllers\Warehouse\BinController;
use App\Http\Controllers\Warehouse\WarehouseController;
use App\Http\Controllers\Warehouse\WarehouseTypeController;
use App\Http\Controllers\Waste\WasteDisposalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route tenant (subdomain company)
|--------------------------------------------------------------------------
|
| Dimuat di bootstrap/app.php dengan tenancy + sesi tenant. Back-office untuk
| user internal, /portal untuk user klien (BR-PRJ-07).
|
*/

Route::middleware('guest')->group(function (): void {
    // Back-office
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    // Portal klien
    Route::get('/portal/login', [LoginController::class, 'showPortal'])->name('portal.login');
    Route::post('/portal/login', [LoginController::class, 'storePortal'])
        ->middleware('throttle:login')
        ->name('portal.login.store');

    // Lupa & atur ulang password
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:login')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    // Undangan user
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'store'])->name('invitation.store');
});

// Verifikasi dua langkah: sesi login sudah lolos password, menunggu kode.
Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor');
Route::post('/two-factor', [TwoFactorController::class, 'store'])
    ->middleware('throttle:login')
    ->name('two-factor.store');

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Akses dukungan Super Admin (BR-SUB-04, A-180): tautan bertanda tangan dari
// layar Super Admin; GET menampilkan konfirmasi, POST membuka sesi hanya-baca.
Route::middleware('signed')->group(function (): void {
    Route::get('/support/enter/{supportAccess}', [SupportSessionController::class, 'show'])->whereNumber('supportAccess')->name('support.enter');
    Route::post('/support/enter/{supportAccess}', [SupportSessionController::class, 'store'])->whereNumber('supportAccess')->name('support.enter.store');
});

// Halaman penerima bertoken (A-41, A-231, BR-SJ-05): tanpa akun; token + OTP
// adalah identitasnya, lalu lintasnya dibatasi.
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/terima/{token}', [DeliveryTokenController::class, 'show'])->name('terima.show');
    Route::post('/terima/{token}/otp', [DeliveryTokenController::class, 'otp'])->name('terima.otp');
    Route::post('/terima/{token}', [DeliveryTokenController::class, 'store'])->name('terima.store');
});

Route::middleware('auth')->group(function (): void {
    // Notifikasi in-app untuk semua user, termasuk klien (Blueprint §10).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [NotificationController::class, 'savePreferences'])->name('notifications.preferences.save');

    // Back-office (user internal)
    Route::middleware('internal')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        // Wizard setup awal company (A-191).
        Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
        Route::post('/setup/terms', [SetupController::class, 'acceptTerms'])->name('setup.terms');
        Route::post('/setup/complete', [SetupController::class, 'complete'])->name('setup.complete');
        // Pengaturan company (11-master §6, A-230): ambang, saklar fitur, zona waktu.
        Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        // Pengaturan dua langkah di profil (10-access §6.2). Tanpa ini seluruh
        // jalur 2FA adalah kode mati: tidak ada yang pernah menulis rahasianya.
        Route::post('/profile/two-factor', [TwoFactorSetupController::class, 'begin'])->name('profile.two-factor.begin');
        Route::post('/profile/two-factor/confirm', [TwoFactorSetupController::class, 'confirm'])->name('profile.two-factor.confirm');
        Route::post('/profile/two-factor/cancel', [TwoFactorSetupController::class, 'cancel'])->name('profile.two-factor.cancel');
        Route::post('/profile/two-factor/disable', [TwoFactorSetupController::class, 'disable'])->name('profile.two-factor.disable');
        Route::post('/profile/two-factor/recovery-codes', [TwoFactorSetupController::class, 'regenerate'])->name('profile.two-factor.regenerate');

        // Unggah tanda tangan & berkas tenant (AD-10). Berkas disajikan lewat
        // route berotorisasi, bukan URL publik.
        Route::post('/profile/signature', [ProfileController::class, 'updateSignature'])->name('profile.signature');
        Route::delete('/profile/signature', [ProfileController::class, 'deleteSignature'])->name('profile.signature.destroy');
        Route::get('/files/signature/{user}', [FileController::class, 'signature'])->name('files.signature');
        Route::get('/files/item-photo/{item}', [FileController::class, 'itemPhoto'])->name('files.item-photo');
        Route::post('/items/{item}/photo', [ItemPhotoController::class, 'store'])->name('items.photo.store');
        Route::delete('/items/{item}/photo', [ItemPhotoController::class, 'destroy'])->name('items.photo.destroy');

        // Pengelolaan pengguna & role (10-access §6.3, §6.4)
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');

        // Struktur organisasi, perangkat, akses dukungan (10-access §6.5–§6.7)
        Route::get('/org', [OrgController::class, 'index'])->name('org.index');
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('/settings/support-access', [SupportAccessController::class, 'index'])
            ->name('support-access.index');

        // Template dokumen & label (18-template-dokumen-label §6). Cetak = GET, hanya membaca.
        Route::get('/print/{type}/{id}', [PrintController::class, 'document'])
            ->whereNumber('id')->name('print.document');
        Route::get('/labels', [PrintController::class, 'labelForm'])->name('labels.index');
        Route::get('/labels/print', [PrintController::class, 'labels'])->name('labels.print');
        Route::get('/settings/document-layout', [DocumentLayoutController::class, 'edit'])->name('document-layout.edit');
        Route::get('/settings/document-layout/preview', [DocumentLayoutController::class, 'preview'])->name('document-layout.preview');
        Route::get('/settings/document-layout/logo', [DocumentLayoutController::class, 'logo'])->name('document-layout.logo');
        Route::post('/settings/document-layout/logo', [DocumentLayoutController::class, 'storeLogo'])->name('document-layout.logo.store');
        Route::delete('/settings/document-layout/logo', [DocumentLayoutController::class, 'destroyLogo'])->name('document-layout.logo.destroy');

        // Master data (11-master §6). Semua transisi status lewat POST, bukan GET.
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');

        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
        // Impor item dari Excel (A-192).
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/{type}/template', [ImportController::class, 'template'])->whereIn('type', ['items', 'projects', 'vendors', 'opening-stock'])->name('imports.template');
        Route::post('/imports/{type}', [ImportController::class, 'store'])->whereIn('type', ['items', 'projects', 'vendors', 'opening-stock'])->name('imports.store');
        Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');

        Route::get('/item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
        Route::get('/uoms', [UomController::class, 'index'])->name('uoms.index');
        Route::get('/references', [ReferenceController::class, 'index'])->name('references.index');

        // Gudang & lokasi (12-warehouse §6).
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
        Route::get('/bins', [BinController::class, 'index'])->name('bins.index');
        Route::get('/warehouse-types', [WarehouseTypeController::class, 'index'])->name('warehouse-types.index');

        // Stok (13-stock §6). Semua GET; mutasi stok hanya lewat dokumen.
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/reservations', [StockController::class, 'reservations'])->name('stock.reservations');
        Route::get('/stock/events', [StockController::class, 'events'])->name('stock.events');
        Route::get('/stock/items/{item}', [StockController::class, 'card'])->name('stock.card');
        Route::get('/settings/stock-period', [StockController::class, 'period'])->name('stock.period');

        // Permintaan material (14-request §6). Transisi status lewat Livewire.
        Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
        Route::get('/requests/{materialRequest}', [RequestController::class, 'show'])->name('requests.show');
        Route::get('/requests/{materialRequest}/edit', [RequestController::class, 'edit'])->name('requests.edit');
        // BR-REQ-10: tanggapan pemohon atas bukti terima (POST, membawa foto).
        Route::post('/requests/{materialRequest}/receipts/{proof}/confirm', [DeliveryReceiptController::class, 'confirm'])->whereNumber('proof')->name('requests.receipt.confirm');
        Route::post('/requests/{materialRequest}/receipts/{proof}/dispute', [DeliveryReceiptController::class, 'dispute'])->whereNumber('proof')->name('requests.receipt.dispute');

        // Picking & pengiriman (15-picking-shipment §6).
        Route::get('/picks', [ShipmentController::class, 'picks'])->name('picks.index');
        Route::get('/picks/{pickTask}', [ShipmentController::class, 'pick'])->name('picks.show');
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        // Berkas bukti terima lewat controller berotorisasi, bukan URL publik (NFR-14).
        Route::get('/shipments/{shipment}/proof/{berkas}', [ShipmentController::class, 'proofFile'])->where('berkas', 'foto|ttd|baris-[0-9]+')->name('shipments.proof.file');
        Route::get('/discrepancies', [ShipmentController::class, 'discrepancies'])->name('discrepancies.index');

        // Penerimaan, put-away, retur ke vendor (19-receipt-putaway §6). Halaman
        // saja; semua transisi status lewat aksi Livewire (POST), tidak lewat GET.
        Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/receipts/create', [ReceiptController::class, 'create'])->name('receipts.create');
        Route::get('/receipts/{goodsReceipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{goodsReceipt}/edit', [ReceiptController::class, 'edit'])->name('receipts.edit');
        Route::get('/putaways', [ReceiptController::class, 'putaways'])->name('putaways.index');
        Route::get('/putaways/{putawayTask}', [ReceiptController::class, 'putaway'])->name('putaways.show');
        Route::get('/vendor-returns', [ReceiptController::class, 'vendorReturns'])->name('vendor-returns.index');
        Route::get('/vendor-returns/create', [ReceiptController::class, 'createVendorReturn'])->name('vendor-returns.create');
        Route::get('/vendor-returns/{vendorReturn}', [ReceiptController::class, 'vendorReturn'])->name('vendor-returns.show');

        // Approval (20-approval §6). Halaman saja; keputusan, eskalasi, dan
        // delegasi lewat aksi Livewire (POST), tidak lewat GET.
        Route::get('/approvals', [ApprovalController::class, 'inbox'])->name('approval.inbox');
        Route::get('/approval-rules', [ApprovalController::class, 'rules'])->name('approval.rules.index');
        Route::get('/approval-rules/create', [ApprovalController::class, 'createRule'])->name('approval.rules.create');
        Route::get('/approval-rules/{approvalRule}/edit', [ApprovalController::class, 'editRule'])->name('approval.rules.edit');
        Route::get('/approval-delegations', [ApprovalController::class, 'delegations'])->name('approval.delegations');
        Route::get('/approval-simulation', [ApprovalController::class, 'simulation'])->name('approval.simulation');

        // Stock opname & penyesuaian stok (21-opname-penyesuaian §6). Halaman dan
        // laporan PDF saja (GET, baca); semua transisi lewat aksi Livewire (POST).
        Route::get('/counts', [CountController::class, 'index'])->name('counts.index');
        Route::get('/counts/create', [CountController::class, 'create'])->name('counts.create');
        Route::get('/counts/{stockCount}', [CountController::class, 'show'])->name('counts.show');
        Route::get('/counts/{stockCount}/report', [CountController::class, 'report'])->name('counts.report');
        Route::get('/count-tasks', [CountController::class, 'tasks'])->name('count-tasks.index');
        Route::get('/count-tasks/{countAssignment}', [CountController::class, 'task'])->name('count-tasks.show');
        Route::get('/adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/create', [AdjustmentController::class, 'create'])->name('adjustments.create');
        Route::get('/adjustments/{stockAdjustment}', [AdjustmentController::class, 'show'])->name('adjustments.show');

        // Transfer & retur dari proyek (22-retur-transfer §6). Halaman saja (GET);
        // semua transisi lewat aksi Livewire (POST).
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/create', [ReturnController::class, 'create'])->name('returns.create');
        Route::get('/returns/{goodsReturn}', [ReturnController::class, 'show'])->name('returns.show');

        // Pemakaian material di Gudang Site (23-pemakaian §6). Halaman saja (GET);
        // konfirmasi, pembalik, approval, dan batal lewat aksi Livewire (POST).
        Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
        Route::get('/issues/create', [IssueController::class, 'create'])->name('issues.create');
        Route::get('/issues/{materialIssue}', [IssueController::class, 'show'])->name('issues.show');
        Route::get('/issues/{materialIssue}/edit', [IssueController::class, 'edit'])->name('issues.edit');

        // Konversi material & berita acara waste (24-konversi-waste §6). Halaman
        // lewat GET; transisi lewat Livewire (POST), penutupan WST lewat POST
        // form unggah bukti.
        Route::get('/conversions', [ConversionController::class, 'index'])->name('conversions.index');
        Route::get('/conversions/create', [ConversionController::class, 'create'])->name('conversions.create');
        Route::get('/conversions/{conversion}', [ConversionController::class, 'show'])->name('conversions.show');
        Route::get('/conversions/{conversion}/edit', [ConversionController::class, 'edit'])->name('conversions.edit');
        Route::get('/waste-disposals', [WasteDisposalController::class, 'index'])->name('waste-disposals.index');
        Route::get('/waste-disposals/create', [WasteDisposalController::class, 'create'])->name('waste-disposals.create');
        Route::get('/waste-disposals/{wasteDisposal}', [WasteDisposalController::class, 'show'])->name('waste-disposals.show');
        Route::get('/waste-disposals/{wasteDisposal}/evidence', [WasteDisposalController::class, 'evidence'])->name('waste-disposals.evidence');
        Route::post('/waste-disposals/{wasteDisposal}/close', [WasteDisposalController::class, 'close'])->name('waste-disposals.close');

        // Aset dipinjamkan (25-aset §6). Halaman lewat GET; pemeriksaan aset
        // lewat POST form unggah foto; aksi lain lewat Livewire (POST).
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/{serial}', [AssetController::class, 'show'])->name('assets.show');
        Route::get('/asset-handovers', [AssetController::class, 'handovers'])->name('asset-handovers.index');
        Route::get('/asset-handovers/{assetHandover}', [AssetController::class, 'handover'])->name('asset-handovers.show');
        Route::post('/asset-handovers/{assetHandover}/inspect', [AssetController::class, 'inspect'])->name('asset-handovers.inspect');
        Route::get('/asset-inspections/{assetInspection}/photo', [AssetController::class, 'photo'])->name('asset-inspections.photo');

        // Purchase Request (26-purchase-request §6). Halaman lewat GET; ajukan,
        // approval, catatan pemesanan, dan batal lewat aksi Livewire (POST).
        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
        Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
        Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');

        // Purchasing inti Fase 1b (purchasing/02 §6). Halaman lewat GET; ajukan,
        // approval, ETA, batal, dan tutup sisa lewat aksi Livewire (POST).
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::get('/vendor-prices', [PurchaseOrderController::class, 'vendorPrices'])->name('vendor-prices.index');

        // Tagihan langganan Admin Company (17-platform-login §6.2). Unggah bukti
        // bayar tetap boleh saat langganan ditangguhkan (BR-SUB-02).
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/invoices/{invoice}/payments', [BillingController::class, 'store'])->whereNumber('invoice')->name('billing.payment.store');
        Route::get('/billing/payments/{payment}/proof', [BillingController::class, 'proof'])->whereNumber('payment')->name('billing.payment.proof');

        // Laporan lintas modul (Access §9, Master §9, Warehouse §9).
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    // Portal klien
    Route::middleware('portal')->prefix('portal')->name('portal.')->group(function (): void {
        Route::get('/', PortalDashboardController::class)->name('dashboard');
        Route::get('/requests', [PortalRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{materialRequest}', [PortalRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{materialRequest}/receipts/{proof}/confirm', [DeliveryReceiptController::class, 'confirm'])->whereNumber('proof')->name('requests.receipt.confirm');
        Route::post('/requests/{materialRequest}/receipts/{proof}/dispute', [DeliveryReceiptController::class, 'dispute'])->whereNumber('proof')->name('requests.receipt.dispute');
        // Retur klien (BR-RET-05): komponen yang sama dengan back-office.
        Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/create', [ReturnController::class, 'create'])->name('returns.create');
        Route::get('/returns/{goodsReturn}', [ReturnController::class, 'show'])->name('returns.show');
    });
});
