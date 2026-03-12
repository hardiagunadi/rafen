<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaMultiSessionDevice;
use App\Services\WaMultiSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('allows super admin to restart tenant wa session through gateway control endpoint', function () {
    config()->set('wa.multi_session.public_url', 'https://gw.example');
    config()->set('wa.multi_session.auth_token', '');

    Http::fake([
        'https://gw.example/api/v2/sessions/restart' => Http::response([
            'status' => true,
            'message' => 'Session restart triggered',
            'data' => [
                'session' => 'tenant-2',
                'status' => 'restarting',
            ],
        ], 200),
    ]);

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $tenant = User::factory()->create([
        'role' => 'administrator',
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'tenant-token-001',
    ]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('tenant-settings.wa-session-control', ['action' => 'restart']), [
            'tenant_id' => $tenant->id,
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    Http::assertSent(function ($request) use ($tenant) {
        return str_contains($request->url(), '/api/v2/sessions/restart')
            && ($request->header('X-Session-Id')[0] ?? null) === 'tenant-'.$tenant->id
            && data_get($request->data(), 'session') === 'tenant-'.$tenant->id;
    });
});

it('rejects wa multi session service control for non super admin', function () {
    $tenantAdmin = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($tenantAdmin)
        ->postJson(route('tenant-settings.wa-service-control', ['action' => 'status']));

    $response->assertForbidden();
});

it('uses manager response for super admin service control', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $mock = \Mockery::mock(WaMultiSessionManager::class);
    $mock->shouldReceive('status')->once()->andReturn([
        'running' => true,
        'name' => 'wa-multi-session',
        'host' => '127.0.0.1',
        'port' => 3100,
        'url' => 'http://127.0.0.1:3100',
        'pm2_pid' => 9999,
        'pm2_status' => 'online',
        'log_file' => storage_path('logs/wa-multi-session.log'),
    ]);

    $this->app->instance(WaMultiSessionManager::class, $mock);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('tenant-settings.wa-service-control', ['action' => 'status']));

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'data' => [
                'running' => true,
                'pm2_pid' => 9999,
            ],
        ]);
});

it('allows tenant admin to create and set default wa device', function () {
    $tenantAdmin = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenantAdmin->id);

    $storeResponse = $this->actingAs($tenantAdmin)
        ->postJson(route('tenant-settings.wa-devices.store'), [
            'device_name' => 'Perangkat Kasir',
        ]);

    $storeResponse->assertSuccessful()
        ->assertJsonPath('success', true);

    $deviceId = (int) $storeResponse->json('data.id');

    $defaultResponse = $this->actingAs($tenantAdmin)
        ->postJson(route('tenant-settings.wa-devices.default', ['device' => $deviceId]));

    $defaultResponse->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('wa_multi_session_devices', [
        'id' => $deviceId,
        'user_id' => $tenantAdmin->id,
        'is_default' => true,
    ]);
});

it('blocks sub-user from managing wa devices', function () {
    $owner = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    $subUser = User::factory()->create([
        'parent_id' => $owner->id,
        'role' => 'staff',
    ]);

    TenantSettings::getOrCreate($owner->id);
    $device = WaMultiSessionDevice::query()->create([
        'user_id' => $owner->id,
        'device_name' => 'Owner Device',
        'session_id' => 'tenant-'.$owner->id.'-owner-device',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($subUser)
        ->deleteJson(route('tenant-settings.wa-devices.destroy', ['device' => $device->id]))
        ->assertForbidden();
});

it('uses selected tenant wa settings when super admin tests template message', function () {
    config()->set('wa.multi_session.public_url', 'https://gw.example');
    config()->set('wa.multi_session.auth_token', 'device-token');

    Http::fake([
        'https://gw.example/api/v2/send-message' => Http::response([
            'status' => true,
            'data' => [
                'messages' => [
                    ['status' => 'queued', 'ref_id' => 'ref-template-001'],
                ],
            ],
        ], 200),
    ]);

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
        'role' => 'administrator',
    ]);

    $tenant = User::factory()->create([
        'role' => 'administrator',
    ]);

    TenantSettings::getOrCreate($tenant->id)->update([
        'wa_gateway_url' => 'https://gw.example',
        'wa_gateway_token' => 'device-token',
        'business_phone' => '081234567890',
        'wa_template_invoice' => 'Halo {name}, ini test invoice',
    ]);

    $response = $this->actingAs($superAdmin)
        ->postJson(route('tenant-settings.test-template'), [
            'tenant_id' => $tenant->id,
            'type' => 'invoice',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    Http::assertSent(function ($request) use ($tenant) {
        return str_contains($request->url(), '/api/v2/send-message')
            && ($request->header('X-Session-Id')[0] ?? null) === 'tenant-'.$tenant->id;
    });
});

it('shows wa gateway overview and dedicated devices tab for tenant admin', function () {
    $tenantAdmin = User::factory()->create([
        'is_super_admin' => false,
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);

    TenantSettings::getOrCreate($tenantAdmin->id);

    $overviewResponse = $this->actingAs($tenantAdmin)->get(route('wa-gateway.index'));
    $overviewResponse->assertSuccessful()
        ->assertSee('Integrasi WhatsApp Gateway')
        ->assertSee('Manajemen Device');

    $devicesResponse = $this->actingAs($tenantAdmin)->get(route('wa-gateway.index', ['tab' => 'devices']));
    $devicesResponse->assertSuccessful()
        ->assertSee('Manajemen Device WA')
        ->assertSee('Scan QR WhatsApp');
});
