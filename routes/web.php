<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BandwidthProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MikrotikConnectionController;
use App\Http\Controllers\ProfileGroupController;
use App\Http\Controllers\RadiusAccountController;
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
    Route::get('reports/income', IncomeReportController::class)->name('reports.income');
    Route::post('mikrotik-connections/test', [MikrotikConnectionController::class, 'test'])->name('mikrotik-connections.test');
    Route::post('radius/restart', [DashboardController::class, 'restartRadius'])->name('radius.restart');
    Route::resource('mikrotik-connections', MikrotikConnectionController::class);
    Route::resource('radius-accounts', RadiusAccountController::class);
    Route::delete('bandwidth-profiles/bulk-destroy', [BandwidthProfileController::class, 'bulkDestroy'])->name('bandwidth-profiles.bulk-destroy');
    Route::resource('bandwidth-profiles', BandwidthProfileController::class);
    Route::delete('profile-groups/bulk-destroy', [ProfileGroupController::class, 'bulkDestroy'])->name('profile-groups.bulk-destroy');
    Route::resource('profile-groups', ProfileGroupController::class);
    Route::delete('hotspot-profiles/bulk-destroy', [HotspotProfileController::class, 'bulkDestroy'])->name('hotspot-profiles.bulk-destroy');
    Route::resource('hotspot-profiles', HotspotProfileController::class);
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::patch('invoices/{invoice}/renew', [InvoiceController::class, 'renew'])->name('invoices.renew');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::resource('users', UserManagementController::class);
    Route::resource('ppp-profiles', \App\Http\Controllers\PppProfileController::class);
    Route::delete('ppp-profiles/bulk-destroy', [\App\Http\Controllers\PppProfileController::class, 'bulkDestroy'])->name('ppp-profiles.bulk-destroy');
    Route::resource('ppp-users', \App\Http\Controllers\PppUserController::class);
    Route::delete('ppp-users/bulk-destroy', [\App\Http\Controllers\PppUserController::class, 'bulkDestroy'])->name('ppp-users.bulk-destroy');
});
