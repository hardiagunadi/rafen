<?php

use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
