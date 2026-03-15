<?php

use App\Http\Controllers\ActiveSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\CustomerMapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreeRadiusSettingsController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\HotspotUserController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\IsolirPageController;
use App\Http\Controllers\MikrotikConnectionController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\OltConnectionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileGroupController;
use App\Http\Controllers\RadiusAccountController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SystemToolController;
use App\Http\Controllers\TeknisiSetoranController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\WaBlastController;
use App\Http\Controllers\WaMultiSessionProxyController;
use App\Http\Controllers\WaWebhookController;
use App\Http\Controllers\CpeController;

use App\Http\Controllers\WgSettingsController;
use Illuminate\Support\Facades\Route;

Route::any('/wa-multi-session/{path?}', WaMultiSessionProxyController::class)->where('path', '.*');


Route::get('login', [LoginController::class, 'show'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');
Route::get('logout', function () {
    return redirect()->route('login')->with('status', 'Anda sudah logout. Gunakan tombol logout (POST) untuk keluar.');
});
Route::get('register', [RegisterController::class, 'show'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('api-dashboard', [DashboardController::class, 'apiDashboard'])->name('dashboard.api');
    Route::get('api-dashboard/data', [DashboardController::class, 'apiDashboardData'])->name('dashboard.api.data');
    Route::get('api-dashboard/menu-data', [DashboardController::class, 'apiDashboardMenu'])->name('dashboard.api.menu');
    Route::get('api-dashboard/traffic', [DashboardController::class, 'apiDashboardTraffic'])->name('dashboard.api.traffic');
    // PPP Secret CRUD via MikroTik API
    Route::post('api-dashboard/ppp-secret', [DashboardController::class, 'pppSecretStore'])->name('dashboard.api.ppp-secret.store');
    Route::put('api-dashboard/ppp-secret/{id}', [DashboardController::class, 'pppSecretUpdate'])->name('dashboard.api.ppp-secret.update');
    Route::delete('api-dashboard/ppp-secret/{id}', [DashboardController::class, 'pppSecretDestroy'])->name('dashboard.api.ppp-secret.destroy');
    Route::post('api-dashboard/ppp-active/{id}/disconnect', [DashboardController::class, 'pppActiveDisconnect'])->name('dashboard.api.ppp-active.disconnect');
    Route::middleware('tenant.module:hotspot')->group(function () {
        // Hotspot User CRUD via MikroTik API
        Route::post('api-dashboard/hotspot-user', [DashboardController::class, 'hotspotUserStore'])->name('dashboard.api.hotspot-user.store');
        Route::put('api-dashboard/hotspot-user/{id}', [DashboardController::class, 'hotspotUserUpdate'])->name('dashboard.api.hotspot-user.update');
        Route::delete('api-dashboard/hotspot-user/{id}', [DashboardController::class, 'hotspotUserDestroy'])->name('dashboard.api.hotspot-user.destroy');
        Route::post('api-dashboard/hotspot-active/{id}/disconnect', [DashboardController::class, 'hotspotActiveDisconnect'])->name('dashboard.api.hotspot-active.disconnect');
        // Hotspot IP Binding CRUD via MikroTik API
        Route::post('api-dashboard/hotspot-ip-binding', [DashboardController::class, 'hotspotIpBindingStore'])->name('dashboard.api.hotspot-ip-binding.store');
        Route::put('api-dashboard/hotspot-ip-binding/{id}', [DashboardController::class, 'hotspotIpBindingUpdate'])->name('dashboard.api.hotspot-ip-binding.update');
        Route::delete('api-dashboard/hotspot-ip-binding/{id}', [DashboardController::class, 'hotspotIpBindingDestroy'])->name('dashboard.api.hotspot-ip-binding.destroy');
    });
    // PPPoE Server CRUD via MikroTik API
    Route::post('api-dashboard/pppoe-server', [DashboardController::class, 'pppoeServerStore'])->name('dashboard.api.pppoe-server.store');
    Route::put('api-dashboard/pppoe-server/{id}', [DashboardController::class, 'pppoeServerUpdate'])->name('dashboard.api.pppoe-server.update');
    Route::delete('api-dashboard/pppoe-server/{id}', [DashboardController::class, 'pppoeServerDestroy'])->name('dashboard.api.pppoe-server.destroy');
    Route::get('reports/income', IncomeReportController::class)->name('reports.income');
    Route::post('reports/income/expenses', [IncomeReportController::class, 'storeExpense'])->name('reports.expenses.store');

    // Log Aplikasi
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('login', [\App\Http\Controllers\LogController::class, 'loginIndex'])->name('login');
        Route::get('login/datatable', [\App\Http\Controllers\LogController::class, 'loginDatatable'])->name('login.datatable');
        Route::get('activity', [\App\Http\Controllers\LogController::class, 'activityIndex'])->name('activity');
        Route::get('activity/data', [\App\Http\Controllers\LogController::class, 'activityData'])->name('activity.data');
        Route::get('bg-process', [\App\Http\Controllers\LogController::class, 'bgProcessIndex'])->name('bg-process');
        Route::get('bg-process/datatable', [\App\Http\Controllers\LogController::class, 'bgProcessDatatable'])->name('bg-process.datatable');
        Route::get('genieacs', [\App\Http\Controllers\LogController::class, 'genieacsIndex'])->name('genieacs');
        Route::get('genieacs/data', [\App\Http\Controllers\LogController::class, 'genieacsData'])->name('genieacs.data');
        Route::get('radius-auth', [\App\Http\Controllers\LogController::class, 'radiusAuthIndex'])->name('radius-auth');
        Route::get('radius-auth/datatable', [\App\Http\Controllers\LogController::class, 'radiusAuthDatatable'])->name('radius-auth.datatable');
        Route::get('wa-pengiriman', [\App\Http\Controllers\LogController::class, 'waPengirimanIndex'])->name('wa-pengiriman');
        Route::get('wa-pengiriman/keluar/datatable', [\App\Http\Controllers\LogController::class, 'waBlastDatatable'])->name('wa-pengiriman.keluar.datatable');
        Route::get('wa-pengiriman/masuk/datatable', [\App\Http\Controllers\LogController::class, 'waWebhookDatatable'])->name('wa-pengiriman.masuk.datatable');
        // backward-compat redirect
        Route::get('wa-blast', fn() => redirect()->route('logs.wa-pengiriman'))->name('wa-blast');
        Route::get('wa-blast/datatable', [\App\Http\Controllers\LogController::class, 'waBlastDatatable'])->name('wa-blast.datatable');
    });
    Route::post('mikrotik-connections/test', [MikrotikConnectionController::class, 'test'])->name('mikrotik-connections.test');
    Route::post('mikrotik-connections/{mikrotikConnection}/ping', [MikrotikConnectionController::class, 'pingNow'])->name('mikrotik-connections.ping-now');
    Route::post('mikrotik-connections/{mikrotikConnection}/isolir-reset', [MikrotikConnectionController::class, 'isolirReset'])->name('mikrotik-connections.isolir-reset');
    Route::post('mikrotik-connections/radius-sync-clients', [MikrotikConnectionController::class, 'syncRadiusClients'])->name('mikrotik-connections.radius-sync-clients');
    Route::get('mikrotik-connections/datatable', [MikrotikConnectionController::class, 'datatable'])->name('mikrotik-connections.datatable');
    Route::post('olt-connections/auto-detect-model', [OltConnectionController::class, 'autoDetectModel'])->name('olt-connections.auto-detect-model');
    Route::post('olt-connections/auto-detect-oid', [OltConnectionController::class, 'autoDetectOid'])->name('olt-connections.auto-detect-oid');
    Route::post('olt-connections/{oltConnection}/poll', [OltConnectionController::class, 'poll'])->name('olt-connections.poll');
    Route::post('olt-connections/{oltConnection}/onu/reboot', [OltConnectionController::class, 'rebootOnu'])->name('olt-connections.onu-reboot');
    Route::get('olt-connections/{oltConnection}/onu/status', [OltConnectionController::class, 'onuStatus'])->name('olt-connections.onu-status');
    Route::get('olt-connections/{oltConnection}/onu/alarms', [OltConnectionController::class, 'onuAlarms'])->name('olt-connections.onu-alarms');
    Route::get('olt-connections/{oltConnection}/polling-status', [OltConnectionController::class, 'pollingStatus'])->name('olt-connections.polling-status');
    Route::get('olt-connections/{oltConnection}/datatable', [OltConnectionController::class, 'datatable'])->name('olt-connections.datatable');
    Route::post('radius/restart', [DashboardController::class, 'restartRadius'])->name('radius.restart');
    Route::post('genieacs/restart', [DashboardController::class, 'restartGenieacs'])->name('genieacs.restart');
    Route::resource('mikrotik-connections', MikrotikConnectionController::class);
    Route::resource('olt-connections', OltConnectionController::class);

    // CPE Management (GenieACS)
    Route::get('cpe', [CpeController::class, 'index'])->name('cpe.index');
    Route::get('cpe/datatable', [CpeController::class, 'datatable'])->name('cpe.datatable');
    Route::get('cpe/unlinked', [CpeController::class, 'unlinkedDevices'])->name('cpe.unlinked');
    Route::post('cpe/link', [CpeController::class, 'linkDevice'])->name('cpe.link');
    Route::get('cpe/search-ppp-users', [CpeController::class, 'searchPppUsers'])->name('cpe.search-ppp-users');
    Route::prefix('ppp-users/{pppUserId}/cpe')->group(function () {
        Route::get('', [CpeController::class, 'show'])->name('cpe.show');
        Route::post('sync', [CpeController::class, 'sync'])->name('cpe.sync');
        Route::post('reboot', [CpeController::class, 'reboot'])->name('cpe.reboot');
        Route::post('wifi', [CpeController::class, 'updateWifi'])->name('cpe.update-wifi');
        Route::post('pppoe', [CpeController::class, 'updatePppoe'])->name('cpe.update-pppoe');
        Route::post('refresh', [CpeController::class, 'refreshParams'])->name('cpe.refresh');
        Route::get('info', [CpeController::class, 'getInfo'])->name('cpe.info');
        Route::delete('', [CpeController::class, 'destroy'])->name('cpe.destroy');
        // Multi-SSID
        Route::post('wifi/{wlanIdx}', [CpeController::class, 'updateWifiByIndex'])->name('cpe.wifi-by-index');
        // WAN connections
        Route::get('wan', [CpeController::class, 'getWanConnections'])->name('cpe.wan-list');
        Route::put('wan/{wanIdx}/{cdIdx}/{connIdx}', [CpeController::class, 'updateWanConnection'])->name('cpe.wan-update');
    });

    Route::get('radius-accounts/datatable', [RadiusAccountController::class, 'datatable'])->name('radius-accounts.datatable');
    Route::resource('radius-accounts', RadiusAccountController::class);
    Route::get('bandwidth-profiles/datatable', [BandwidthProfileController::class, 'datatable'])->name('bandwidth-profiles.datatable');
    Route::delete('bandwidth-profiles/bulk-destroy', [BandwidthProfileController::class, 'bulkDestroy'])->name('bandwidth-profiles.bulk-destroy');
    Route::resource('bandwidth-profiles', BandwidthProfileController::class);
    Route::post('profile-groups/{profileGroup}/export', [ProfileGroupController::class, 'export'])->name('profile-groups.export');
    Route::post('profile-groups/export-bulk', [ProfileGroupController::class, 'bulkExport'])->name('profile-groups.export-bulk');
    Route::delete('profile-groups/bulk-destroy', [ProfileGroupController::class, 'bulkDestroy'])->name('profile-groups.bulk-destroy');
    Route::get('profile-groups/datatable', [ProfileGroupController::class, 'datatable'])->name('profile-groups.datatable');
    Route::get('profile-groups/mikrotik-queues', [ProfileGroupController::class, 'mikrotikQueues'])->name('profile-groups.mikrotik-queues');
    Route::resource('profile-groups', ProfileGroupController::class);
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('hotspot-profiles/datatable', [HotspotProfileController::class, 'datatable'])->name('hotspot-profiles.datatable');
        Route::delete('hotspot-profiles/bulk-destroy', [HotspotProfileController::class, 'bulkDestroy'])->name('hotspot-profiles.bulk-destroy');
        Route::resource('hotspot-profiles', HotspotProfileController::class);
    });
    Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/nota', [InvoiceController::class, 'nota'])->name('invoices.nota');
    Route::get('invoices/nota-bulk', [InvoiceController::class, 'notaBulk'])->name('invoices.nota-bulk');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::get('invoices/{invoice}/pay', fn ($invoice) => redirect()->route('invoices.show', $invoice));
    Route::post('invoices/{invoice}/renew', [InvoiceController::class, 'renew'])->name('invoices.renew');
    Route::post('invoices/{invoice}/send-wa', [InvoiceController::class, 'sendWa'])->name('invoices.send-wa');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('teknisi-setoran/datatable', [TeknisiSetoranController::class, 'datatable'])->name('teknisi-setoran.datatable');
    Route::get('teknisi-setoran/teknisi-list', [TeknisiSetoranController::class, 'teknisiList'])->name('teknisi-setoran.teknisi-list');
    Route::get('teknisi-setoran/{teknisiSetoran}', [TeknisiSetoranController::class, 'show'])->name('teknisi-setoran.show');
    Route::get('teknisi-setoran', [TeknisiSetoranController::class, 'index'])->name('teknisi-setoran.index');
    Route::post('teknisi-setoran', [TeknisiSetoranController::class, 'store'])->name('teknisi-setoran.store');
    Route::post('teknisi-setoran/{teknisiSetoran}/submit', [TeknisiSetoranController::class, 'submit'])->name('teknisi-setoran.submit');
    Route::post('teknisi-setoran/{teknisiSetoran}/verify', [TeknisiSetoranController::class, 'verify'])->name('teknisi-setoran.verify');
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
    Route::delete('ppp-profiles/bulk-destroy', [\App\Http\Controllers\PppProfileController::class, 'bulkDestroy'])->name('ppp-profiles.bulk-destroy');
    Route::resource('ppp-profiles', \App\Http\Controllers\PppProfileController::class);
    Route::get('ppp-users/datatable', [\App\Http\Controllers\PppUserController::class, 'datatable'])->name('ppp-users.datatable');
    Route::get('ppp-users/autocomplete', [\App\Http\Controllers\PppUserController::class, 'autocomplete'])->name('ppp-users.autocomplete');
    Route::get('ppp-users/generate-customer-id', [\App\Http\Controllers\PppUserController::class, 'generateCustomerId'])->name('ppp-users.generate-customer-id');
    Route::delete('ppp-users/bulk-destroy', [\App\Http\Controllers\PppUserController::class, 'bulkDestroy'])->name('ppp-users.bulk-destroy');
    Route::post('ppp-users/{pppUser}/toggle-status', [\App\Http\Controllers\PppUserController::class, 'toggleStatus'])->name('ppp-users.toggle-status');
    Route::get('ppp-users/{pppUser}/invoice-datatable', [\App\Http\Controllers\PppUserController::class, 'invoiceDatatable'])->name('ppp-users.invoice-datatable');
    Route::get('ppp-users/{pppUser}/dialup-datatable', [\App\Http\Controllers\PppUserController::class, 'dialupDatatable'])->name('ppp-users.dialup-datatable');
    Route::post('ppp-users/{pppUser}/add-invoice', [\App\Http\Controllers\PppUserController::class, 'addInvoice'])->name('ppp-users.add-invoice');
    Route::post('ppp-users/{pppUser}/disconnect', [\App\Http\Controllers\PppUserController::class, 'disconnect'])->name('ppp-users.disconnect');
    Route::get('ppp-users/{pppUser}/nota-aktivasi', [\App\Http\Controllers\PppUserController::class, 'notaAktivasi'])->name('ppp-users.nota-aktivasi');
    Route::resource('ppp-users', \App\Http\Controllers\PppUserController::class);
    Route::get('odps/datatable', [OdpController::class, 'datatable'])->name('odps.datatable');
    Route::get('odps/generate-code', [OdpController::class, 'generateCode'])->name('odps.generate-code');
    Route::get('odps/autocomplete', [OdpController::class, 'autocomplete'])->name('odps.autocomplete');
    Route::resource('odps', OdpController::class);
    Route::get('customer-map', [CustomerMapController::class, 'index'])->name('customer-map.index');
    Route::get('customer-map/cache-config', [CustomerMapController::class, 'cacheConfig'])->name('customer-map.cache-config');
    Route::get('customer-map/cache-tiles', [CustomerMapController::class, 'cacheTiles'])->name('customer-map.cache-tiles');
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('hotspot-users/datatable', [HotspotUserController::class, 'datatable'])->name('hotspot-users.datatable');
        Route::get('hotspot-users/autocomplete', [HotspotUserController::class, 'autocomplete'])->name('hotspot-users.autocomplete');
        Route::get('hotspot-users/generate-customer-id', [HotspotUserController::class, 'generateCustomerId'])->name('hotspot-users.generate-customer-id');
        Route::delete('hotspot-users/bulk-destroy', [HotspotUserController::class, 'bulkDestroy'])->name('hotspot-users.bulk-destroy');
        Route::post('hotspot-users/{hotspotUser}/renew', [HotspotUserController::class, 'renew'])->name('hotspot-users.renew');
        Route::post('hotspot-users/{hotspotUser}/toggle-status', [HotspotUserController::class, 'toggleStatus'])->name('hotspot-users.toggle-status');
        Route::resource('hotspot-users', HotspotUserController::class);
    });
    Route::get('vouchers/datatable', [VoucherController::class, 'datatable'])->name('vouchers.datatable');
    Route::delete('vouchers/bulk-destroy', [VoucherController::class, 'bulkDestroy'])->name('vouchers.bulk-destroy');
    Route::get('vouchers/{batch}/print', [VoucherController::class, 'printBatch'])->name('vouchers.print');
    Route::resource('vouchers', VoucherController::class);
    Route::get('help', [HelpController::class, 'index'])->name('help.index');
    Route::get('help/{slug}', [HelpController::class, 'topic'])->name('help.topic');
    Route::view('branding-preview', 'branding.preview')->name('branding.preview');

    Route::get('sessions/pppoe', [ActiveSessionController::class, 'pppoe'])->name('sessions.pppoe');
    Route::get('sessions/pppoe/datatable', [ActiveSessionController::class, 'pppoeDatatable'])->name('sessions.pppoe.datatable');
    Route::get('sessions/pppoe-inactive', [ActiveSessionController::class, 'pppoeInactive'])->name('sessions.pppoe-inactive');
    Route::get('sessions/pppoe-inactive/datatable', [ActiveSessionController::class, 'pppoeInactiveDatatable'])->name('sessions.pppoe-inactive.datatable');
    Route::middleware('tenant.module:hotspot')->group(function () {
        Route::get('sessions/hotspot', [ActiveSessionController::class, 'hotspot'])->name('sessions.hotspot');
        Route::get('sessions/hotspot/datatable', [ActiveSessionController::class, 'hotspotDatatable'])->name('sessions.hotspot.datatable');
        Route::get('sessions/hotspot-inactive', [ActiveSessionController::class, 'hotspotInactive'])->name('sessions.hotspot-inactive');
        Route::get('sessions/hotspot-inactive/datatable', [ActiveSessionController::class, 'hotspotInactiveDatatable'])->name('sessions.hotspot-inactive.datatable');
    });
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
        Route::get('/pending', [PaymentController::class, 'pendingIndex'])->name('pending');
        Route::get('/pending/datatable', [PaymentController::class, 'pendingDatatable'])->name('pending.datatable');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::get('/invoice/{invoice}/create', [PaymentController::class, 'createForInvoice'])->name('create-for-invoice');
        Route::post('/invoice/{invoice}', [PaymentController::class, 'storeForInvoice'])->name('store-for-invoice');
        Route::get('/{payment}/check-status', [PaymentController::class, 'checkStatus'])->name('check-status');
        Route::get('/invoice/{invoice}/manual', [PaymentController::class, 'manualForm'])->name('manual-form');
        Route::post('/invoice/{invoice}/manual', [PaymentController::class, 'manualConfirmation'])->name('manual-confirmation');
        Route::post('/{payment}/confirm', [PaymentController::class, 'confirmManual'])->name('confirm-manual');
        Route::post('/{payment}/reject', [PaymentController::class, 'rejectManual'])->name('reject-manual');
    });
    Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');

    // Tenant Settings
    Route::prefix('settings/tenant')->name('tenant-settings.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'index'])->name('index');
        Route::put('/business', [TenantSettingsController::class, 'updateBusiness'])->name('update-business');
        Route::put('/payment', [TenantSettingsController::class, 'updatePayment'])->name('update-payment');
        Route::put('/modules', [TenantSettingsController::class, 'updateModules'])->name('update-modules');
        Route::put('/map-cache', [TenantSettingsController::class, 'updateMapCache'])->name('update-map-cache');
        Route::post('/test-tripay', [TenantSettingsController::class, 'testTripay'])->name('test-tripay');
        Route::post('/test-midtrans', [TenantSettingsController::class, 'testMidtrans'])->name('test-midtrans');
        Route::post('/test-duitku', [TenantSettingsController::class, 'testDuitku'])->name('test-duitku');
        Route::get('/payment-channels', [TenantSettingsController::class, 'getPaymentChannels'])->name('payment-channels');
        Route::post('/logo', [TenantSettingsController::class, 'uploadLogo'])->name('upload-logo');

        // Bank accounts
        Route::post('/bank-accounts', [TenantSettingsController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::put('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'updateBankAccount'])->name('bank-accounts.update');
        Route::delete('/bank-accounts/{bankAccount}', [TenantSettingsController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');
        Route::post('/bank-accounts/{bankAccount}/primary', [TenantSettingsController::class, 'setPrimaryBankAccount'])->name('bank-accounts.set-primary');

        // WhatsApp settings
        Route::get('/wa', function (\Illuminate\Http\Request $request) {
            $params = [];
            if ($request->user()?->isSuperAdmin()) {
                $tenantId = $request->integer('tenant_id');
                if ($tenantId > 0) {
                    $params['tenant_id'] = $tenantId;
                }
            }

            return redirect()->route('wa-gateway.index', $params);
        })->name('wa-redirect');
        Route::put('/wa', [TenantSettingsController::class, 'updateWa'])->name('update-wa');
        Route::post('/test-wa', [TenantSettingsController::class, 'testWa'])->name('test-wa');
        Route::post('/test-template', [TenantSettingsController::class, 'testTemplate'])->name('test-template');
        Route::post('/wa/session/{action}', [TenantSettingsController::class, 'sessionControl'])->name('wa-session-control');
        Route::post('/wa/service/{action}', [TenantSettingsController::class, 'serviceControl'])->name('wa-service-control');
        Route::get('/wa/devices', [TenantSettingsController::class, 'waDevices'])->name('wa-devices.index');
        Route::post('/wa/devices', [TenantSettingsController::class, 'storeWaDevice'])->name('wa-devices.store');
        Route::post('/wa/devices/{device}/default', [TenantSettingsController::class, 'setDefaultWaDevice'])->name('wa-devices.default');
        Route::post('/wa/devices/{device}/test', [TenantSettingsController::class, 'testWaDevice'])->name('wa-devices.test');
        Route::delete('/wa/devices/{device}', [TenantSettingsController::class, 'destroyWaDevice'])->name('wa-devices.destroy');

        // Isolir page settings
        Route::put('/isolir', [TenantSettingsController::class, 'updateIsolir'])->name('update-isolir');
        Route::get('/isolir-preview', [TenantSettingsController::class, 'isolirPreview'])->name('isolir-preview');
        // GenieACS settings
        Route::put('/genieacs', [TenantSettingsController::class, 'updateGenieacs'])->name('update-genieacs');
    });

    // WA Gateway (halaman tersendiri)
    Route::prefix('settings/wa-gateway')->name('wa-gateway.')->group(function () {
        Route::get('/', [TenantSettingsController::class, 'waGateway'])->name('index');
    });

    // WA Blast
    Route::prefix('wa-blast')->name('wa-blast.')->group(function () {
        Route::get('/', [WaBlastController::class, 'index'])->name('index');
        Route::get('/preview', [WaBlastController::class, 'preview'])->name('preview');
        Route::post('/send', [WaBlastController::class, 'send'])->name('send');
    });

    // Chat WA Inbox
    Route::prefix('wa-chat')->name('wa-chat.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WaChatController::class, 'index'])->name('index');
        Route::get('/conversations', [\App\Http\Controllers\WaChatController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{waConversation}/messages', [\App\Http\Controllers\WaChatController::class, 'show'])->name('show');
        Route::post('/conversations/{waConversation}/reply', [\App\Http\Controllers\WaChatController::class, 'reply'])->name('reply');
        Route::post('/conversations/{waConversation}/reply-image', [\App\Http\Controllers\WaChatController::class, 'replyImage'])->name('reply-image');
        Route::post('/conversations/{waConversation}/resolve', [\App\Http\Controllers\WaChatController::class, 'markResolved'])->name('resolve');
        Route::post('/conversations/{waConversation}/open', [\App\Http\Controllers\WaChatController::class, 'markOpen'])->name('open');
        Route::post('/conversations/{waConversation}/assign', [\App\Http\Controllers\WaChatController::class, 'assign'])->name('assign');
        Route::post('/conversations/{waConversation}/resume-bot', [\App\Http\Controllers\WaChatController::class, 'resumeBot'])->name('resume-bot');
        Route::delete('/conversations/{waConversation}', [\App\Http\Controllers\WaChatController::class, 'destroy'])->name('destroy');
        Route::get('/search-customers', [\App\Http\Controllers\WaChatController::class, 'searchCustomers'])->name('search-customers');
    });

    // Keyword Rules Bot WA
    Route::prefix('wa-keyword-rules')->name('wa-keyword-rules.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WaKeywordRuleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\WaKeywordRuleController::class, 'store'])->name('store');
        Route::put('/{waKeywordRule}', [\App\Http\Controllers\WaKeywordRuleController::class, 'update'])->name('update');
        Route::delete('/{waKeywordRule}', [\App\Http\Controllers\WaKeywordRuleController::class, 'destroy'])->name('destroy');
    });

    // Tiket WA
    // Outage Tracking — Pelacakan Gangguan Jaringan
    Route::prefix('outages')->name('outages.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OutageController::class, 'index'])->name('index');
        Route::get('/datatable', [\App\Http\Controllers\OutageController::class, 'datatable'])->name('datatable');
        Route::get('/create', [\App\Http\Controllers\OutageController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\OutageController::class, 'store'])->name('store');
        Route::post('/affected-users-preview', [\App\Http\Controllers\OutageController::class, 'affectedUsersPreview'])->name('affected-users-preview');
        Route::post('/test-blast', [\App\Http\Controllers\OutageController::class, 'testBlast'])->name('test-blast');
        Route::get('/{outage}', [\App\Http\Controllers\OutageController::class, 'show'])->name('show');
        Route::get('/{outage}/edit', [\App\Http\Controllers\OutageController::class, 'edit'])->name('edit');
        Route::put('/{outage}', [\App\Http\Controllers\OutageController::class, 'update'])->name('update');
        Route::delete('/{outage}', [\App\Http\Controllers\OutageController::class, 'destroy'])->name('destroy');
        Route::post('/{outage}/updates', [\App\Http\Controllers\OutageController::class, 'addUpdate'])->name('updates.store');
        Route::post('/{outage}/resolve', [\App\Http\Controllers\OutageController::class, 'resolve'])->name('resolve');
        Route::post('/{outage}/blast', [\App\Http\Controllers\OutageController::class, 'blast'])->name('blast');
        Route::get('/{outage}/affected-users', [\App\Http\Controllers\OutageController::class, 'affectedUsers'])->name('affected-users');
        Route::post('/{outage}/assign', [\App\Http\Controllers\OutageController::class, 'assign'])->name('assign');
    });

    Route::prefix('wa-tickets')->name('wa-tickets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WaTicketController::class, 'index'])->name('index');
        Route::get('/datatable', [\App\Http\Controllers\WaTicketController::class, 'datatable'])->name('datatable');
        Route::post('/', [\App\Http\Controllers\WaTicketController::class, 'store'])->name('store');
        Route::get('/{waTicket}', [\App\Http\Controllers\WaTicketController::class, 'show'])->name('show');
        Route::put('/{waTicket}', [\App\Http\Controllers\WaTicketController::class, 'update'])->name('update');
        Route::post('/{waTicket}/assign', [\App\Http\Controllers\WaTicketController::class, 'assign'])->name('assign');
        Route::post('/{waTicket}/notes', [\App\Http\Controllers\WaTicketController::class, 'addNote'])->name('notes.store');
        Route::delete('/{waTicket}', [\App\Http\Controllers\WaTicketController::class, 'destroy'])->name('destroy');
    });

    // Jadwal Shift
    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ShiftController::class, 'index'])->name('index');
        Route::get('/my', [\App\Http\Controllers\ShiftController::class, 'mySchedule'])->name('my');
        Route::get('/schedule', [\App\Http\Controllers\ShiftController::class, 'schedule'])->name('schedule');
        Route::post('/schedule', [\App\Http\Controllers\ShiftController::class, 'storeSchedule'])->name('schedule.store');
        Route::post('/schedule/bulk', [\App\Http\Controllers\ShiftController::class, 'bulkSchedule'])->name('schedule.bulk');
        Route::delete('/schedule/{shiftSchedule}', [\App\Http\Controllers\ShiftController::class, 'destroySchedule'])->name('schedule.destroy');
        Route::get('/definitions', [\App\Http\Controllers\ShiftController::class, 'definitions'])->name('definitions');
        Route::post('/definitions', [\App\Http\Controllers\ShiftController::class, 'storeDefinition'])->name('definitions.store');
        Route::put('/definitions/{shiftDefinition}', [\App\Http\Controllers\ShiftController::class, 'updateDefinition'])->name('definitions.update');
        Route::delete('/definitions/{shiftDefinition}', [\App\Http\Controllers\ShiftController::class, 'destroyDefinition'])->name('definitions.destroy');
        Route::get('/swap-requests', [\App\Http\Controllers\ShiftController::class, 'swapRequests'])->name('swap-requests');
        Route::post('/swap-requests', [\App\Http\Controllers\ShiftController::class, 'requestSwap'])->name('swap-requests.store');
        Route::post('/swap-requests/{shiftSwapRequest}/review', [\App\Http\Controllers\ShiftController::class, 'reviewSwap'])->name('swap-requests.review');
        Route::post('/send-reminders', [\App\Http\Controllers\ShiftController::class, 'sendReminders'])->name('send-reminders');
    });
});

// Tool Sistem (auth required, fitur sensitif dibatasi di controller)
Route::middleware('auth')->prefix('tools')->name('tools.')->group(function () {
    // Cek Pemakaian — semua user terotentikasi
    Route::get('usage', [SystemToolController::class, 'usageIndex'])->name('usage');
    Route::get('usage/data', [SystemToolController::class, 'usageData'])->name('usage.data');

    // Impor User — tenant admin & super admin
    Route::get('import', [SystemToolController::class, 'importIndex'])->name('import');
    Route::post('import/preview', [SystemToolController::class, 'importPreview'])->name('import.preview');
    Route::post('import/confirm', [SystemToolController::class, 'importConfirm'])->name('import.confirm');
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

// Halaman isolir publik (no auth required) — diakses via DNAT Mikrotik
Route::get('/isolir/{userId}', [IsolirPageController::class, 'show'])->name('isolir.show')->where('userId', '[0-9]+');

// Halaman status gangguan publik (no auth required) — link dibagikan via WA
Route::get('/status/{token}', [\App\Http\Controllers\OutageStatusController::class, 'show'])->name('outage.public-status');

// Portal pembayaran pelanggan (no auth required) — diakses via link WA
Route::get('/bayar/{token}', [PaymentController::class, 'customerPortal'])->name('customer.invoice');
Route::post('/bayar/{token}/manual', [PaymentController::class, 'customerManualConfirmation'])->name('customer.invoice.manual');
Route::post('/bayar/{token}/gateway', [PaymentController::class, 'customerStorePayment'])->name('customer.invoice.gateway');

// Payment Callbacks (no auth required)
Route::post('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::post('/payment/callback/midtrans', [PaymentController::class, 'callbackMidtrans'])->name('payment.callback.midtrans');
Route::post('/payment/callback/duitku', [PaymentController::class, 'callbackDuitku'])->name('payment.callback.duitku');
Route::post('/subscription/payment/callback', [SubscriptionController::class, 'paymentCallback'])->name('subscription.payment.callback');

// WA Gateway Webhooks (no auth required)
// GET = verification ping from gateway, POST = actual webhook payload
Route::match(['GET', 'POST'], '/webhook/wa', [WaWebhookController::class, 'ingest'])->name('wa.webhook.ingest');
Route::match(['GET', 'POST'], '/webhook/wa/session', [WaWebhookController::class, 'session'])->name('wa.webhook.session');
Route::match(['GET', 'POST'], '/webhook/wa/message', [WaWebhookController::class, 'message'])->name('wa.webhook.message');
Route::match(['GET', 'POST'], '/webhook/wa/auto-reply', [WaWebhookController::class, 'autoReply'])->name('wa.webhook.auto-reply');
Route::match(['GET', 'POST'], '/webhook/wa/status', [WaWebhookController::class, 'status'])->name('wa.webhook.status');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}', [WaWebhookController::class, 'ingest'])->whereNumber('tenant')->name('wa.webhook.ingest.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/session', [WaWebhookController::class, 'session'])->whereNumber('tenant')->name('wa.webhook.session.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/message', [WaWebhookController::class, 'message'])->whereNumber('tenant')->name('wa.webhook.message.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/auto-reply', [WaWebhookController::class, 'autoReply'])->whereNumber('tenant')->name('wa.webhook.auto-reply.tenant');
Route::match(['GET', 'POST'], '/webhook/wa/{tenant}/{secret}/status', [WaWebhookController::class, 'status'])->whereNumber('tenant')->name('wa.webhook.status.tenant');
Route::match(['GET', 'POST'], '/webhook', [WaWebhookController::class, 'ingest'])->name('wa.webhook.ingest.compat');
Route::match(['GET', 'POST'], '/webhook/session', [WaWebhookController::class, 'session'])->name('wa.webhook.session.compat');
Route::match(['GET', 'POST'], '/webhook/message', [WaWebhookController::class, 'message'])->name('wa.webhook.message.compat');
Route::match(['GET', 'POST'], '/webhook/auto-reply', [WaWebhookController::class, 'autoReply'])->name('wa.webhook.auto-reply.compat');
Route::match(['GET', 'POST'], '/webhook/status', [WaWebhookController::class, 'status'])->name('wa.webhook.status.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}', [WaWebhookController::class, 'ingest'])->whereNumber('tenant')->name('wa.webhook.ingest.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/session', [WaWebhookController::class, 'session'])->whereNumber('tenant')->name('wa.webhook.session.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/message', [WaWebhookController::class, 'message'])->whereNumber('tenant')->name('wa.webhook.message.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/auto-reply', [WaWebhookController::class, 'autoReply'])->whereNumber('tenant')->name('wa.webhook.auto-reply.tenant.compat');
Route::match(['GET', 'POST'], '/webhook/{tenant}/{secret}/status', [WaWebhookController::class, 'status'])->whereNumber('tenant')->name('wa.webhook.status.tenant.compat');

// Portal Pelanggan PPPoE — per-tenant slug: /portal/{slug}/...
Route::prefix('portal/{portalSlug}')->name('portal.')->group(function () {
    Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');

    Route::middleware('portal.auth')->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');
        Route::get('/invoices', [PortalDashboardController::class, 'invoices'])->name('invoices');
        Route::get('/account', [PortalDashboardController::class, 'account'])->name('account');
        Route::post('/change-password', [PortalDashboardController::class, 'changePassword'])->name('change-password');
        Route::post('/tickets', [PortalDashboardController::class, 'storeTicket'])->name('tickets.store');
        Route::post('/wifi', [PortalDashboardController::class, 'updateWifi'])->name('wifi.update');
    });
});

// Legacy fallback — /portal/login (tanpa slug) redirect ke /portal/login jika hanya 1 tenant atau tampilkan pilihan
Route::prefix('portal')->name('portal.legacy.')->group(function () {
    Route::get('/login', [PortalAuthController::class, 'showLoginLegacy'])->name('login');
});

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
    Route::post('/tenants/{tenant}/subscriptions/{subscription}/confirm-payment', [SuperAdminController::class, 'confirmSubscriptionPayment'])->name('tenants.subscriptions.confirm-payment');
    Route::get('/tenants/{tenant}/change-plan/preview', [SuperAdminController::class, 'changePlanPreview'])->name('tenants.change-plan.preview');
    Route::post('/tenants/{tenant}/change-plan', [SuperAdminController::class, 'changePlan'])->name('tenants.change-plan');

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
