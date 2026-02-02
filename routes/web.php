<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreeRadiusSettingsController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MikrotikConnectionController;
use App\Http\Controllers\OvpnSettingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileGroupController;
use App\Http\Controllers\RadiusAccountController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\UserManagementController;
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
    Route::post('mikrotik-connections/test', [MikrotikConnectionController::class, 'test'])->name('mikrotik-connections.test');
    Route::post('radius/restart', [DashboardController::class, 'restartRadius'])->name('radius.restart');
    Route::resource('mikrotik-connections', MikrotikConnectionController::class);
    Route::resource('radius-accounts', RadiusAccountController::class);
    Route::delete('bandwidth-profiles/bulk-destroy', [BandwidthProfileController::class, 'bulkDestroy'])->name('bandwidth-profiles.bulk-destroy');
    Route::resource('bandwidth-profiles', BandwidthProfileController::class);
    Route::post('profile-groups/{profileGroup}/export', [ProfileGroupController::class, 'export'])->name('profile-groups.export');
    Route::post('profile-groups/export-bulk', [ProfileGroupController::class, 'bulkExport'])->name('profile-groups.export-bulk');
    Route::delete('profile-groups/bulk-destroy', [ProfileGroupController::class, 'bulkDestroy'])->name('profile-groups.bulk-destroy');
    Route::resource('profile-groups', ProfileGroupController::class);
    Route::delete('hotspot-profiles/bulk-destroy', [HotspotProfileController::class, 'bulkDestroy'])->name('hotspot-profiles.bulk-destroy');
    Route::resource('hotspot-profiles', HotspotProfileController::class);
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::patch('invoices/{invoice}/renew', [InvoiceController::class, 'renew'])->name('invoices.renew');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::resource('users', UserManagementController::class);
    Route::get('settings/freeradius', [FreeRadiusSettingsController::class, 'index'])->name('settings.freeradius');
    Route::post('settings/freeradius/sync', [FreeRadiusSettingsController::class, 'sync'])->name('settings.freeradius.sync');
    Route::get('settings/ovpn', [OvpnSettingsController::class, 'index'])->name('settings.ovpn');
    Route::post('settings/ovpn/clients', [OvpnSettingsController::class, 'store'])->name('settings.ovpn.clients.store');
    Route::patch('settings/ovpn/clients/{ovpnClient}', [OvpnSettingsController::class, 'update'])->name('settings.ovpn.clients.update');
    Route::delete('settings/ovpn/clients/{ovpnClient}', [OvpnSettingsController::class, 'destroy'])->name('settings.ovpn.clients.destroy');
    Route::post('settings/ovpn/clients/{ovpnClient}/sync', [OvpnSettingsController::class, 'sync'])->name('settings.ovpn.clients.sync');
    Route::resource('ppp-profiles', \App\Http\Controllers\PppProfileController::class);
    Route::delete('ppp-profiles/bulk-destroy', [\App\Http\Controllers\PppProfileController::class, 'bulkDestroy'])->name('ppp-profiles.bulk-destroy');
    Route::resource('ppp-users', \App\Http\Controllers\PppUserController::class);
    Route::delete('ppp-users/bulk-destroy', [\App\Http\Controllers\PppUserController::class, 'bulkDestroy'])->name('ppp-users.bulk-destroy');

    // Subscription routes for tenants
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('plans');
        Route::post('/subscribe/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::get('/payment/{subscription}', [SubscriptionController::class, 'payment'])->name('payment');
        Route::post('/payment/{subscription}', [SubscriptionController::class, 'processPayment'])->name('process-payment');
        Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');
        Route::post('/renew', [SubscriptionController::class, 'renew'])->name('renew');
        Route::get('/history', [SubscriptionController::class, 'history'])->name('history');
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
