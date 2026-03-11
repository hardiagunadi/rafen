<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats due date as dd-mm-yyyy in invoice datatable', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => '2026-03-25',
        'customer_id' => '000000000100',
        'customer_name' => 'Pelanggan Format Tanggal',
        'username' => 'pelanggan-format-tanggal',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-FMT-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Format',
        'total' => 150000,
        'due_date' => '2026-03-25',
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.due_date', '25-03-2026');
});

it('shows unpaid isolated invoices with isolated label on datatable', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'isolir',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000000101',
        'customer_name' => 'Pelanggan Isolir',
        'username' => 'pelanggan-isolir',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-ISO-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Isolir',
        'total' => 150000,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.status_label', 'Belum Bayar - Terisolir')
        ->assertJsonPath('data.0.status_variant', 'danger');
});

it('isolates overdue unpaid users when invoice datatable is requested', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenantAdmin->id)->update([
        'auto_isolate_unpaid' => true,
    ]);

    $radiusSyncMock = \Mockery::mock(RadiusReplySynchronizer::class);
    $radiusSyncMock->shouldReceive('syncSingleUser')
        ->once()
        ->with(\Mockery::type(PppUser::class))
        ->andReturnNull();
    app()->instance(RadiusReplySynchronizer::class, $radiusSyncMock);

    $isolirSyncMock = \Mockery::mock(IsolirSynchronizer::class);
    $isolirSyncMock->shouldReceive('isolate')
        ->once()
        ->with(\Mockery::type(PppUser::class))
        ->andReturnNull();
    app()->instance(IsolirSynchronizer::class, $isolirSyncMock);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000000102',
        'customer_name' => 'Pelanggan Overdue',
        'username' => 'pelanggan-overdue',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-OVD-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Overdue',
        'total' => 175000,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.status_label', 'Belum Bayar - Terisolir')
        ->assertJsonPath('data.0.status_variant', 'danger');

    $pppUser->refresh();

    expect($pppUser->status_akun)->toBe('isolir');
});

it('shows active unpaid invoices with active-belum-bayar label on datatable', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->addDays(7)->toDateString(),
        'customer_id' => '000000000103',
        'customer_name' => 'Pelanggan Aktif Belum Lunas',
        'username' => 'pelanggan-aktif-belum-lunas',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-ACT-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Aktif',
        'total' => 180000,
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.status_label', 'Aktif - Belum Bayar')
        ->assertJsonPath('data.0.status_variant', 'warning')
        ->assertJsonPath('data.0.can_renew', true)
        ->assertJsonPath('data.0.can_pay', true)
        ->assertJsonPath('data.0.can_mark_paid', false);
});

it('shows mark-paid action only after unpaid invoice is renewed', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $pppUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->addDays(7)->toDateString(),
        'customer_id' => '000000000106',
        'customer_name' => 'Pelanggan Renew Aksi',
        'username' => 'pelanggan-renew-aksi',
    ]);

    $invoice = Invoice::query()->create([
        'invoice_number' => 'INV-RNW-ACT-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $pppUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $pppUser->customer_id,
        'customer_name' => $pppUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Renew Aksi',
        'total' => 190000,
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->postJson(route('invoices.renew', $invoice))
        ->assertSuccessful();

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => $invoice->invoice_number],
        ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.invoice_number', $invoice->invoice_number)
        ->assertJsonPath('data.0.status_label', 'Aktif - Belum Bayar')
        ->assertJsonPath('data.0.can_renew', false)
        ->assertJsonPath('data.0.can_pay', false)
        ->assertJsonPath('data.0.can_mark_paid', true);
});

it('filters invoice datatable by active unpaid and isolated unpaid status', function () {
    $tenantAdmin = User::factory()->create([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $activeUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'enable',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->addDays(5)->toDateString(),
        'customer_id' => '000000000104',
        'customer_name' => 'Pelanggan Aktif Filter',
        'username' => 'pelanggan-aktif-filter',
    ]);

    $isolatedUser = PppUser::query()->create([
        'owner_id' => $tenantAdmin->id,
        'status_akun' => 'isolir',
        'status_bayar' => 'belum_bayar',
        'aksi_jatuh_tempo' => 'isolir',
        'jatuh_tempo' => now()->subDay()->toDateString(),
        'customer_id' => '000000000105',
        'customer_name' => 'Pelanggan Isolir Filter',
        'username' => 'pelanggan-isolir-filter',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-FLT-'.now()->format('YmdHis').'01',
        'ppp_user_id' => $activeUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $activeUser->customer_id,
        'customer_name' => $activeUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Aktif Filter',
        'total' => 200000,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => 'unpaid',
    ]);

    Invoice::query()->create([
        'invoice_number' => 'INV-FLT-'.now()->format('YmdHis').'02',
        'ppp_user_id' => $isolatedUser->id,
        'owner_id' => $tenantAdmin->id,
        'customer_id' => $isolatedUser->customer_id,
        'customer_name' => $isolatedUser->customer_name,
        'tipe_service' => 'pppoe',
        'paket_langganan' => 'Paket Isolir Filter',
        'total' => 210000,
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'unpaid',
    ]);

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active_unpaid',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.customer_id', $activeUser->customer_id)
        ->assertJsonPath('data.0.status_label', 'Aktif - Belum Bayar');

    $this->actingAs($tenantAdmin)
        ->getJson(route('invoices.datatable', [
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'status' => 'isolated_unpaid',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.customer_id', $isolatedUser->customer_id)
        ->assertJsonPath('data.0.status_label', 'Belum Bayar - Terisolir');
});
