<?php

use App\Http\Controllers\ActiveSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreeRadiusSettingsController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\HotspotUserController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MikrotikConnectionController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WgSettingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileGroupController;
use App\Http\Controllers\RadiusAccountController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\SystemToolController;
use Illuminate\Support\Facades\Route;

Route::get('login', [LoginController::class, 'show'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');
Route::get('logout', function () {
    return redirect()->route('login')->with('status', 'Anda sudah logout. Gunakan tombol logout (POST) untuk keluar.');
});
Route::get('register', [RegisterController::class, 'show'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('api-dashboard', [DashboardController::class, 'apiDashboard'])->name('dashboard.api');
    Route::get('api-dashboard/data', [DashboardController::class, 'apiDashboardData'])->name('dashboard.api.data');
    Route::get('reports/income', IncomeReportController::class)->name('reports.income');

    // Log Aplikasi
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('login', [\App\Http\Controllers\LogController::class, 'loginIndex'])->name('login');
        Route::get('login/datatable', [\App\Http\Controllers\LogController::class, 'loginDatatable'])->name('login.datatable');
        Route::get('activity', [\App\Http\Controllers\LogController::class, 'activityIndex'])->name('activity');
        Route::get('activity/data', [\App\Http\Controllers\LogController::class, 'activityData'])->name('activity.data');
        Route::get('bg-process', [\App\Http\Controllers\LogController::class, 'bgProcessIndex'])->name('bg-process');
        Route::get('bg-process/datatable', [\App\Http\Controllers\LogController::class, 'bgProcessDatatable'])->name('bg-process.datatable');
        Route::get('radius-auth', [\App\Http\Controllers\LogController::class, 'radiusAuthIndex'])->name('radius-auth');
        Route::get('radius-auth/datatable', [\App\Http\Controllers\LogController::class, 'radiusAuthDatatable'])->name('radius-auth.datatable');
        Route::get('wa-blast', [\App\Http\Controllers\LogController::class, 'waBlastIndex'])->name('wa-blast');
    });
    Route::post('mikrotik-connections/test', [MikrotikConnectionController::class, 'test'])->name('mikrotik-connections.test');
    Route::post('mikrotik-connections/{mikrotikConnection}/ping', [MikrotikConnectionController::class, 'pingNow'])->name('mikrotik-connections.ping-now');
    Route::post('mikrotik-connections/radius-sync-clients', [MikrotikConnectionController::class, 'syncRadiusClients'])->name('mikrotik-connections.radius-sync-clients');
    Route::get('mikrotik-connections/datatable', [MikrotikConnectionController::class, 'datatable'])->name('mikrotik-connections.datatable');
    Route::post('radius/restart', [DashboardController::class, 'restartRadius'])->name('radius.restart');
    Route::resource('mikrotik-connections', MikrotikConnectionController::class);
    Route::get('radius-accounts/datatable', [RadiusAccountController::class, 'datatable'])->name('radius-accounts.datatable');
    Route::resource('radius-accounts', RadiusAccountController::class);
    Route::get('bandwidth-profiles/datatable', [BandwidthProfileController::class, 'datatable'])->name('bandwidth-profiles.datatable');
    Route::delete('bandwidth-profiles/bulk-destroy', [BandwidthProfileController::class, 'bulkDestroy'])->name('bandwidth-profiles.bulk-destroy');
    Route::resource('bandwidth-profiles', BandwidthProfileController::class);
    Route::post('profile-groups/{profileGroup}/export', [ProfileGroupController::class, 'export'])->name('profile-groups.export');
    Route::post('profile-groups/export-bulk', [ProfileGroupController::class, 'bulkExport'])->name('profile-groups.export-bulk');
    Route::delete('profile-groups/bulk-destroy', [ProfileGroupController::class, 'bulkDestroy'])->name('profile-groups.bulk-destroy');
    Route::get('profile-groups/datatable', [ProfileGroupController::class, 'datatable'])->name('profile-groups.datatable');
    Route::resource('profile-groups', ProfileGroupController::class);
    Route::get('hotspot-profiles/datatable', [HotspotProfileController::class, 'datatable'])->name('hotspot-profiles.datatable');
    Route::delete('hotspot-profiles/bulk-destroy', [HotspotProfileController::class, 'bulkDestroy'])->name('hotspot-profiles.bulk-destroy');
    Route::resource('hotspot-profiles', HotspotProfileController::class);
    Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::patch('invoices/{invoice}/renew', [InvoiceController::class, 'renew'])->name('invoices.renew');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('users/datatable', [UserManagementController::class, 'datatable'])->name('users.datatable');
    Route::resource('users', UserManagementController::class);
    Route::get('ppp-profiles/datatable', [\App\Http\Controllers\PppProfileController::class, 'datatable'])->name('ppp-profiles.datatable');
    Route::get('settings/freeradius', [FreeRadiusSettingsController::class, 'index'])->name('settings.freeradius');
    Route::post('settings/freeradius/sync', [FreeRadiusSettingsController::class, 'sync'])->name('settings.freeradius.sync');
    Route::post('settings/freeradius/sync-replies', [FreeRadiusSettingsController::class, 'syncReplies'])->name('settings.freeradius.sync-replies');
    Route::get('settings/wg', [WgSettingsController::class, 'index'])->name('settings.wg');
    Route::post('settings/wg/peers', [WgSettingsController::class, 'store'])->name('settings.wg.peers.store');
    Route::patch('settings/wg/peers/{wgPeer}', [WgSettingsController::class, 'update'])->name('settings.wg.peers.update');
    Route::delete('settings/wg/peers/{wgPeer}', [WgSettingsController::class, 'destroy'])->name('settings.wg.peers.destroy');
    Route::post('settings/wg/peers/{wgPeer}/sync', [WgSettingsController::class, 'sync'])->name('settings.wg.peers.sync');
    Route::post('settings/wg/peers/{wgPeer}/create-nas', [WgSettingsController::class, 'createNas'])->name('settings.wg.peers.create-nas');
    Route::post('settings/wg/peers/{wgPeer}/keygen', [WgSettingsController::class, 'keygen'])->name('settings.wg.peers.keygen');
    Route::post('settings/wg/save-server-keys', [WgSettingsController::class, 'saveServerKeys'])->name('settings.wg.save-server-keys');
    Route::post('settings/wg/save-host', [WgSettingsController::class, 'saveHost'])->name('settings.wg.save-host');
    Route::get('settings/wg/ping', [WgSettingsController::class, 'ping'])->name('settings.wg.ping');
    Route::resource('ppp-profiles', \App\Http\Controllers\PppProfileController::class);
    Route::delete('ppp-profiles/bulk-destroy', [\App\Http\Controllers\PppProfileController::class, 'bulkDestroy'])->name('ppp-profiles.bulk-destroy');
    Route::get('ppp-users/datatable', [\App\Http\Controllers\PppUserController::class, 'datatable'])->name('ppp-users.datatable');
    Route::resource('ppp-users', \App\Http\Controllers\PppUserController::class);
    Route::delete('ppp-users/bulk-destroy', [\App\Http\Controllers\PppUserController::class, 'bulkDestroy'])->name('ppp-users.bulk-destroy');
    Route::get('hotspot-users/datatable', [HotspotUserController::class, 'datatable'])->name('hotspot-users.datatable');
    Route::delete('hotspot-users/bulk-destroy', [HotspotUserController::class, 'bulkDestroy'])->name('hotspot-users.bulk-destroy');
    Route::resource('hotspot-users', HotspotUserController::class);
    Route::get('vouchers/datatable', [VoucherController::class, 'datatable'])->name('vouchers.datatable');
    Route::delete('vouchers/bulk-destroy', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-destroy');
    Route::get('vouchers/{batch}/print', [VoucherController::class, 'printBatch'])->name('vouchers.print');
    Route::resource('vouchers', VoucherController::class);
    Route::get('help', [HelpController::class, 'index'])->name('help.index');
    Route::get('help/{slug}', [HelpController::class, 'topic'])->name('help.topic');

    Route::get('sessions/pppoe', [ActiveSessionController::class, 'pppoe'])->name('sessions.pppoe');
    Route::get('sessions/pppoe/datatable', [ActiveSessionController::class, 'pppoeDatatable'])->name('sessions.pppoe.datatable');
    Route::get('sessions/pppoe-inactive', [ActiveSessionController::class, 'pppoeInactive'])->name('sessions.pppoe-inactive');
    Route::get('sessions/pppoe-inactive/datatable', [ActiveSessionController::class, 'pppoeInactiveDatatable'])->name('sessions.pppoe-inactive.datatable');
    Route::get('sessions/hotspot', [ActiveSessionController::class, 'hotspot'])->name('sessions.hotspot');
    Route::get('sessions/hotspot/datatable', [ActiveSessionController::class, 'hotspotDatatable'])->name('sessions.hotspot.datatable');
    Route::get('sessions/hotspot-inactive', [ActiveSessionController::class, 'hotspotInactive'])->name('sessions.hotspot-inactive');
    Route::get('sessions/hotspot-inactive/datatable', [ActiveSessionController::class, 'hotspotInactiveDatatable'])->name('sessions.hotspot-inactive.datatable');
    Route::post('sessions/refresh-router/{connection}', [ActiveSessionController::class, 'refreshRouter'])->name('sessions.refresh-router');
    Route::post('sessions/refresh-all', [ActiveSessionController::class, 'refreshAll'])->name('sessions.refresh-all');

    // Subscription routes for tenants
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::get('/subscriptions/datatable', [SubscriptionController::class, 'subscriptionsDatatable'])->name('subscriptions-datatable');
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('plans');
        Route::post('/subscribe/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/payment/{subscription}', [SubscriptionController::class, 'payment'])->name('payment');
        Route::post('/payment/{subscription}', [SubscriptionController::class, 'processPayment'])->name('process-payment');
        Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');
        Route::post('/renew', [SubscriptionController::class, 'renew'])->name('renew');
        Route::get('/history', [SubscriptionController::class, 'history'])->name('history');
        Route::get('/history/datatable', [SubscriptionController::class, 'historyDatatable'])->name('history-datatable');
    });

    // Payment routes
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::get('/invoice/{invoice}/create', [PaymentController::class, 'createForInvoice'])->name('create-for-invoice');
        Route::post('/invoice/{invoice}', [PaymentController::class, 'storeForInvoice'])->name('store-for-invoice');
        Route::get('/{payment}/check-status', [PaymentController::class, 'checkStatus'])->name('check-status');
        Route::post('/invoice/{invoice}/manual', [PaymentController::class, 'manualConfirmation'])->name('manual-confirmation');
        Route::post('/{payment}/confirm', [PaymentController::class, 'confirmManual'])->name('confirm-manual');
        Route::post('/{payment}/reject', [PaymentController::class, 'rejectManual'])->name('reject-manual');
    });
    Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');

    // Invoice payment integration
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    // Tenant Settings
    Route::prefix('settings/tenant')->name('tenant-settings.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'index'])->name('index');
        Route::put('/business', [TenantSettingsController::class, 'updateBusiness'])->name('update-business');
        Route::put('/payment', [TenantSettingsController::class, 'updatePayment'])->name('update-payment');
        Route::post('/test-tripay', [TenantSettingsController::class, 'testTripay'])->name('test-tripay');
        Route::get('/payment-channels', [TenantSettingsController::class, 'getPaymentChannels'])->name('payment-channels');
        Route::post('/logo', [TenantSettingsController::class, 'uploadLogo'])->name('upload-logo');

        // Bank accounts
        Route::post('/bank-accounts', [TenantSettingsController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::put('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'updateBankAccount'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
        Route::post('/bank-accounts/{bankAccount}/primary', [TenantSettingsController::class, 'setPrimaryBankAccount'])->name('bank-accounts.set-primary');
    });
});

// Tool Sistem (auth required, fitur sensitif dibatasi di controller)
Route::middleware('auth')->prefix('tools')->name('tools.')->group(function () {
    // Cek Pemakaian — semua user terotentikasi
    Route::get('usage', [SystemToolController::class, 'usageIndex'])->name('usage');
    Route::get('usage/data', [SystemToolController::class, 'usageData'])->name('usage.data');

    // Impor User — tenant admin & super admin
    Route::get('import', [SystemToolController::class, 'importIndex'])->name('import');
    Route::post('import', [SystemToolController::class, 'importStore'])->name('import.store');
    Route::get('import/template/{type}', [SystemToolController::class, 'importTemplate'])->name('import.template');

    // Ekspor User — tenant admin & super admin
    Route::get('export-users', [SystemToolController::class, 'exportUsersIndex'])->name('export-users');
    Route::get('export-users/download', [SystemToolController::class, 'exportUsersDownload'])->name('export-users.download');

    // Ekspor Transaksi — tenant admin & super admin
    Route::get('export-transactions', [SystemToolController::class, 'exportTransactionsIndex'])->name('export-transactions');
    Route::get('export-transactions/download', [SystemToolController::class, 'exportTransactionsDownload'])->name('export-transactions.download');

    // Backup & Restore — super admin only (dibatasi di controller)
    Route::get('backup', [SystemToolController::class, 'backupIndex'])->name('backup');
    Route::post('backup/create', [SystemToolController::class, 'backupCreate'])->name('backup.create');
    Route::get('backup/download', [SystemToolController::class, 'backupDownload'])->name('backup.download');
    Route::post('backup/restore', [SystemToolController::class, 'backupRestore'])->name('backup.restore');
    Route::delete('backup/delete', [SystemToolController::class, 'backupDelete'])->name('backup.delete');

    // Reset Laporan — super admin only
    Route::get('reset-report', [SystemToolController::class, 'resetReportIndex'])->name('reset-report');
    Route::post('reset-report', [SystemToolController::class, 'resetReportExecute'])->name('reset-report.execute');

    // Reset Database — super admin only
    Route::get('reset-database', [SystemToolController::class, 'resetDatabaseIndex'])->name('reset-database');
    Route::post('reset-database', [SystemToolController::class, 'resetDatabaseExecute'])->name('reset-database.execute');
});

// Payment Callbacks (no auth required)
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::post('/subscription/payment/callback', [SubscriptionController::class, 'paymentCallback'])->name('subscription.payment.callback');

// Super Admin Routes
Route::middleware(['auth', \App\Http\Middleware\SuperAdminMiddleware::class])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');

    // Tenant Management
    Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');
    Route::get('/tenants/create', [SuperAdminController::class, 'createTenant'])->name('tenants.create');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [SuperAdminController::class, 'showTenant'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [SuperAdminController::class, 'editTenant'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [SuperAdminController::class, 'updateTenant'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [SuperAdminController::class, 'deleteTenant'])->name('tenants.delete');
    Route::post('/tenants/{tenant}/activate', [SuperAdminController::class, 'activateTenant'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/suspend', [SuperAdminController::class, 'suspendTenant'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/extend', [SuperAdminController::class, 'extendTenant'])->name('tenants.extend');

    // Tenant VPN Management
    Route::get('/tenants/{tenant}/vpn', [SuperAdminController::class, 'vpnSettings'])->name('tenants.vpn');
    Route::put('/tenants/{tenant}/vpn', [SuperAdminController::class, 'updateVpnSettings'])->name('tenants.vpn.update');
    Route::post('/tenants/{tenant}/vpn/generate', [SuperAdminController::class, 'generateVpnCredentials'])->name('tenants.vpn.generate');

    // Subscription Plans
    Route::resource('subscription-plans', SubscriptionPlanController::class);
    Route::post('/subscription-plans/{subscriptionPlan}/toggle-active', [SubscriptionPlanController::class, 'toggleActive'])->name('subscription-plans.toggle-active');

    // Payment Gateways
    Route::get('/payment-gateways', [SuperAdminController::class, 'paymentGateways'])->name('payment-gateways');
    Route::post('/payment-gateways', [SuperAdminController::class, 'storePaymentGateway'])->name('payment-gateways.store');
    Route::put('/payment-gateways/{gateway}', [SuperAdminController::class, 'updatePaymentGateway'])->name('payment-gateways.update');

    // Reports
    Route::get('/reports/revenue', [SuperAdminController::class, 'revenueReport'])->name('reports.revenue');
    Route::get('/reports/tenants', [SuperAdminController::class, 'tenantsReport'])->name('reports.tenants');
});
