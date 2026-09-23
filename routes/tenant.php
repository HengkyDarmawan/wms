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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\ClientController;
use App\Http\Controllers\Master\ItemCategoryController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\ItemPhotoController;
use App\Http\Controllers\Master\ProjectController;
use App\Http\Controllers\Master\ReferenceController;
use App\Http\Controllers\Master\UomController;
use App\Http\Controllers\Master\VendorController;
use App\Http\Controllers\Request\PortalRequestController;
use App\Http\Controllers\Request\RequestController;
use App\Http\Controllers\Shipment\ShipmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Warehouse\BinController;
use App\Http\Controllers\Warehouse\WarehouseController;
use App\Http\Controllers\Warehouse\WarehouseTypeController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Shared\FileController;
use App\Http\Controllers\Shared\ReportController;
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

Route::middleware('auth')->group(function (): void {
    // Back-office (user internal)
    Route::middleware('internal')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
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

        // Master data (11-master §6). Semua transisi status lewat POST, bukan GET.
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');

        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
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

        // Picking & pengiriman (15-picking-shipment §6).
        Route::get('/picks', [ShipmentController::class, 'picks'])->name('picks.index');
        Route::get('/picks/{pickTask}', [ShipmentController::class, 'pick'])->name('picks.show');
        Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        Route::get('/discrepancies', [ShipmentController::class, 'discrepancies'])->name('discrepancies.index');

        // Laporan lintas modul (Access §9, Master §9, Warehouse §9).
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Portal klien
    Route::middleware('portal')->prefix('portal')->name('portal.')->group(function (): void {
        Route::get('/', PortalDashboardController::class)->name('dashboard');
        Route::get('/requests', [PortalRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{materialRequest}', [PortalRequestController::class, 'show'])->name('requests.show');
    });
});
